<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class TripService
{
    public const STATUS_READY = 'ready';

    public const STATUS_LOADING = 'loading';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_SELLING = 'selling';

    public const STATUS_OFFLOADING = 'offloading';

    public const STATUS_IDLE = 'idle';

    public const EVENT_START_TRIP = 'START_TRIP';

    public const EVENT_DEPARTURE = 'DEPARTURE';

    public const EVENT_STATUS_UPDATE = 'STATUS_UPDATE';

    public const EVENT_END_TRIP = 'END_TRIP';

    /**
     * @return array<string, list<string>>
     */
    private function allowedStatusTransitions(): array
    {
        return [
            self::STATUS_READY => [self::STATUS_LOADING],
            self::STATUS_LOADING => [self::STATUS_IN_TRANSIT],
            self::STATUS_IN_TRANSIT => [self::STATUS_SELLING],
            self::STATUS_SELLING => [self::STATUS_OFFLOADING],
            self::STATUS_OFFLOADING => [self::STATUS_IN_TRANSIT],
        ];
    }

    /**
     * @param  array{driver_id: int, car_id: int, zone_id?: int|null, destination?: string|null}  $data
     */
    public function createTrip(int $tenantId, array $data): Trip
    {
        $driverId = (int) $data['driver_id'];
        $carId = (int) $data['car_id'];
        $zoneId = isset($data['zone_id']) ? ($data['zone_id'] !== null ? (int) $data['zone_id'] : null) : null;
        $destination = isset($data['destination']) ? (is_string($data['destination']) ? $data['destination'] : null) : null;

        $this->assertTenantOwnsDriver($tenantId, $driverId);
        $this->assertTenantOwnsCar($tenantId, $carId);

        if ($zoneId !== null) {
            $this->assertTenantOwnsZone($tenantId, $zoneId);
        }

        $this->assertNoActiveTripForDriver($tenantId, $driverId);
        $this->assertNoActiveTripForCar($tenantId, $carId);

        return DB::transaction(function () use ($tenantId, $driverId, $carId, $zoneId, $destination): Trip {
            return Trip::query()->create([
                'tenant_id' => $tenantId,
                'zone_id' => $zoneId,
                'driver_id' => $driverId,
                'car_id' => $carId,
                'destination' => $destination,
                'status' => self::STATUS_READY,
            ]);
        });
    }

    public function startTrip(Trip $trip, ?int $userId): Trip
    {
        if ($trip->status !== self::STATUS_READY) {
            throw new InvalidArgumentException('Trip can only be started when status is ready.');
        }

        return DB::transaction(function () use ($trip, $userId): Trip {
            $now = now();
            $trip->forceFill([
                'start_date' => $now,
                'arrival_time' => $now,
                'status' => self::STATUS_LOADING,
            ])->save();

            $this->recordTripEvent($trip, self::EVENT_START_TRIP, $userId, []);

            return $trip->fresh();
        });
    }

    public function departTrip(Trip $trip, ?int $userId): Trip
    {
        if ($trip->status !== self::STATUS_LOADING) {
            throw new InvalidArgumentException('Trip can only depart when status is loading.');
        }

        return DB::transaction(function () use ($trip, $userId): Trip {
            $trip->forceFill([
                'departure' => now(),
                'status' => self::STATUS_IN_TRANSIT,
            ])->save();

            $this->recordTripEvent($trip, self::EVENT_DEPARTURE, $userId, []);

            return $trip->fresh();
        });
    }

    public function updateStatus(Trip $trip, string $newStatus, ?int $userId): Trip
    {
        $newStatus = trim($newStatus);
        if ($newStatus === $trip->status) {
            throw new InvalidArgumentException('Status must change.');
        }

        if ($newStatus === self::STATUS_IDLE) {
            throw new InvalidArgumentException('Use the end trip action to set idle status.');
        }

        $allowed = $this->allowedStatusTransitions()[$trip->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid status transition from %s to %s.', $trip->status, $newStatus)
            );
        }

        return DB::transaction(function () use ($trip, $newStatus, $userId): Trip {
            $from = $trip->status;
            $trip->forceFill(['status' => $newStatus])->save();
            $this->recordTripEvent($trip, self::EVENT_STATUS_UPDATE, $userId, [
                'from' => $from,
                'to' => $newStatus,
            ]);

            return $trip->fresh();
        });
    }

    public function endTrip(Trip $trip, ?int $userId): Trip
    {
        if ($trip->status !== self::STATUS_IN_TRANSIT) {
            throw new InvalidArgumentException('Trip can only be ended when status is in_transit.');
        }

        return DB::transaction(function () use ($trip, $userId): Trip {
            $trip->forceFill([
                'end_date' => now(),
                'status' => self::STATUS_IDLE,
            ])->save();

            $this->recordTripEvent($trip, self::EVENT_END_TRIP, $userId, []);

            return $trip->fresh();
        });
    }

    /**
     * @param  array{status?: string, driver_id?: int, car_id?: int}  $filters
     * @return EloquentCollection<int, Trip>
     */
    public function listTrips(int $tenantId, array $filters, ?int $driverScopeId = null): EloquentCollection
    {
        $q = Trip::query()->where('tenant_id', $tenantId)->orderByDesc('id');

        if ($driverScopeId !== null) {
            $q->where('driver_id', $driverScopeId);
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $q->where('status', $filters['status']);
        }

        if (isset($filters['driver_id']) && $filters['driver_id'] !== null && $filters['driver_id'] !== '') {
            $q->where('driver_id', (int) $filters['driver_id']);
        }

        if (isset($filters['car_id']) && $filters['car_id'] !== null && $filters['car_id'] !== '') {
            $q->where('car_id', (int) $filters['car_id']);
        }

        return $q->with(['driver', 'car', 'zone'])->get();
    }

    /**
     * @return array{
     *     trip: Trip,
     *     driver: Driver|null,
     *     car: Car|null,
     *     timeline: EloquentCollection<int, TripEvent>,
     *     inventory_summary: array<string, mixed>,
     *     sales_summary: list<array<string, mixed>>
     * }
     */
    public function getTripDetails(int $tenantId, Trip $trip): array
    {
        if ((int) $trip->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Trip does not belong to this tenant.');
        }

        $trip->loadMissing(['driver', 'car', 'zone']);
        $timeline = $trip->tripEvents()->orderBy('created_at')->orderBy('id')->get();

        $onHand = Inventory::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $trip->car_id)
            ->with(['product:id,item'])
            ->get(['id', 'product_id', 'quantity']);

        $txByType = InventoryTransaction::query()
            ->where('trip_id', $trip->id)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->all();

        $salesQuery = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('trip_id', $trip->id);

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'deleted_at')) {
            $salesQuery->whereNull('deleted_at');
        }

        $salesSummary = $salesQuery
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(total_price) as total_price')
            ->groupBy('product_id')
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'quantity' => (float) $row->quantity,
                'total_price' => (string) $row->total_price,
            ])
            ->values()
            ->all();

        return [
            'trip' => $trip,
            'driver' => $trip->driver,
            'car' => $trip->car,
            'timeline' => $timeline,
            'inventory_summary' => [
                'on_hand' => $onHand->map(fn (Inventory $row) => [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product !== null ? (string) $row->product->item : '',
                    'quantity' => (string) $row->quantity,
                ])->values()->all(),
                'transactions_by_type_for_trip' => $txByType,
            ],
            'sales_summary' => $salesSummary,
        ];
    }

    private function recordTripEvent(Trip $trip, string $eventType, ?int $userId, array $payload): TripEvent
    {
        return TripEvent::query()->create([
            'tenant_id' => $trip->tenant_id,
            'trip_id' => $trip->id,
            'event_type' => $eventType,
            'user_id' => $userId,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    private function assertTenantOwnsDriver(int $tenantId, int $driverId): void
    {
        $exists = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Driver does not belong to this tenant.');
        }
    }

    private function assertTenantOwnsCar(int $tenantId, int $carId): void
    {
        $exists = Car::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $carId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Car does not belong to this tenant.');
        }
    }

    private function assertTenantOwnsZone(int $tenantId, int $zoneId): void
    {
        $exists = Zone::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $zoneId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Zone does not belong to this tenant.');
        }
    }

    private function assertNoActiveTripForDriver(int $tenantId, int $driverId, ?int $exceptTripId = null): void
    {
        $q = Trip::query()
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereNull('end_date');

        if ($exceptTripId !== null) {
            $q->where('id', '!=', $exceptTripId);
        }

        if ($q->exists()) {
            throw new InvalidArgumentException('Driver already has an active trip.');
        }
    }

    private function assertNoActiveTripForCar(int $tenantId, int $carId, ?int $exceptTripId = null): void
    {
        $q = Trip::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->whereNull('end_date');

        if ($exceptTripId !== null) {
            $q->where('id', '!=', $exceptTripId);
        }

        if ($q->exists()) {
            throw new InvalidArgumentException('Car already has an active trip.');
        }
    }
}
