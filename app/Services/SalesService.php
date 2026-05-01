<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SalesService
{
    public const EVENT_SALE = 'SALE';

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array{
     *     trip_id: int,
     *     product_id: int,
     *     customer_id: int,
     *     quantity: int|float|string,
     *     total_price: int|float|string
     * }  $data
     */
    public function createSale(int $tenantId, User $actor, array $data): Sale
    {
        $trip = Trip::query()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $data['trip_id'])
            ->first();

        if ($trip === null) {
            throw new InvalidArgumentException('Trip not found.');
        }

        if ($trip->status !== TripService::STATUS_SELLING) {
            throw new InvalidArgumentException('Sales can only be recorded while the trip status is selling.');
        }

        $this->assertDriverRoleCanUseTrip($actor, $tenantId, $trip);

        $productId = (int) $data['product_id'];
        $customerId = (int) $data['customer_id'];

        if (! $this->tenantOwnsProduct($tenantId, $productId)) {
            throw new InvalidArgumentException('Product does not belong to this tenant.');
        }

        if (! $this->tenantOwnsCustomer($tenantId, $customerId)) {
            throw new InvalidArgumentException('Customer does not belong to this tenant.');
        }

        $carId = (int) $trip->car_id;
        $driverId = (int) $trip->driver_id;
        $userId = (int) $actor->id;
        $quantity = $data['quantity'];
        $totalPrice = $data['total_price'];

        return DB::transaction(function () use (
            $tenantId,
            $trip,
            $carId,
            $driverId,
            $productId,
            $customerId,
            $quantity,
            $totalPrice,
            $userId,
        ): Sale {
            $inv = $this->inventory->applySale(
                $tenantId,
                $carId,
                $productId,
                $quantity,
                $userId,
                (int) $trip->id,
                null,
            );

            $sale = Sale::query()->create([
                'tenant_id' => $tenantId,
                'trip_id' => (int) $trip->id,
                'driver_id' => $driverId,
                'customer_id' => $customerId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
            ]);

            InventoryTransaction::query()
                ->whereKey($inv['transaction_id'])
                ->update(['sale_id' => $sale->id]);

            TripEvent::query()->create([
                'tenant_id' => $tenantId,
                'trip_id' => (int) $trip->id,
                'event_type' => self::EVENT_SALE,
                'user_id' => $userId,
                'quantity' => round((float) $quantity, 2),
                'amount' => round((float) $totalPrice, 2),
                'product_id' => $productId,
                'metadata' => [
                    'sale_id' => (int) $sale->id,
                    'customer_id' => $customerId,
                ],
            ]);

            return $sale->fresh(['trip', 'driver', 'customer', 'product']);
        });
    }

    /**
     * @param  array{
     *     trip_id?: int|null,
     *     driver_id?: int|null,
     *     product_id?: int|null,
     *     customer_id?: int|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     * @return EloquentCollection<int, Sale>
     */
    public function getSales(int $tenantId, array $filters, ?int $driverScopeId = null): EloquentCollection
    {
        $q = Sale::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id');

        if ($driverScopeId !== null) {
            $q->where('driver_id', $driverScopeId);
        }

        if (isset($filters['trip_id']) && $filters['trip_id'] !== null && $filters['trip_id'] !== '') {
            $q->where('trip_id', (int) $filters['trip_id']);
        }

        if (isset($filters['driver_id']) && $filters['driver_id'] !== null && $filters['driver_id'] !== '') {
            $q->where('driver_id', (int) $filters['driver_id']);
        }

        if (isset($filters['product_id']) && $filters['product_id'] !== null && $filters['product_id'] !== '') {
            $q->where('product_id', (int) $filters['product_id']);
        }

        if (isset($filters['customer_id']) && $filters['customer_id'] !== null && $filters['customer_id'] !== '') {
            $q->where('customer_id', (int) $filters['customer_id']);
        }

        $dateFrom = $filters['date_from'] ?? null;
        if (is_string($dateFrom) && $dateFrom !== '') {
            $q->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $filters['date_to'] ?? null;
        if (is_string($dateTo) && $dateTo !== '') {
            $q->whereDate('created_at', '<=', $dateTo);
        }

        return $q->with(['trip', 'driver', 'customer', 'product'])->get();
    }

    /**
     * @return EloquentCollection<int, Sale>
     */
    public function getDriverSales(int $tenantId, int $driverId): EloquentCollection
    {
        return $this->getSales($tenantId, [], $driverId);
    }

    public function deleteSale(Sale $sale, User $actor, int $tenantId): void
    {
        if ((int) $sale->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Sale does not belong to this tenant.');
        }

        $this->assertCanMutateSale($actor, $tenantId, $sale);

        if ($sale->trashed()) {
            throw new InvalidArgumentException('Sale is already deleted.');
        }

        $trip = Trip::withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $sale->trip_id)
            ->first();

        if ($trip === null) {
            throw new InvalidArgumentException('Trip not found for this sale.');
        }

        DB::transaction(function () use ($sale, $actor, $tenantId, $trip): void {
            $this->inventory->applyAdjustment(
                $tenantId,
                (int) $trip->car_id,
                (int) $sale->product_id,
                'increase',
                $sale->quantity,
                (int) $actor->id,
                (int) $sale->trip_id,
            );

            $sale->delete();
        });
    }

    /**
     * @param  array{total_price: int|float|string}  $data
     */
    public function updateSale(Sale $sale, array $data, User $actor, int $tenantId): Sale
    {
        if ((int) $sale->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Sale does not belong to this tenant.');
        }

        $this->assertCanMutateSale($actor, $tenantId, $sale);

        $sale->forceFill([
            'total_price' => $data['total_price'],
        ])->save();

        return $sale->fresh(['trip', 'driver', 'customer', 'product']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function resolveDriverScopeId(User $user, int $tenantId): ?int
    {
        if ($user->role !== 'driver') {
            return null;
        }

        $driver = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->id)
            ->first();

        if ($driver !== null) {
            return (int) $driver->id;
        }

        $driverByName = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('full_name', $user->name)
            ->first();

        return $driverByName !== null ? (int) $driverByName->id : null;
    }

    private function assertDriverRoleCanUseTrip(User $actor, int $tenantId, Trip $trip): void
    {
        if ($actor->role !== 'driver') {
            return;
        }

        $driverId = $this->resolveDriverScopeId($actor, $tenantId);

        if ($driverId === null) {
            throw new InvalidArgumentException('No driver profile is linked to this user.');
        }

        if ($driverId !== (int) $trip->driver_id) {
            throw new InvalidArgumentException('You may only record sales for your own active trip.');
        }
    }

    private function assertCanMutateSale(User $actor, int $tenantId, Sale $sale): void
    {
        if ($actor->role !== 'driver') {
            return;
        }

        $driverId = $this->resolveDriverScopeId($actor, $tenantId);

        if ($driverId === null) {
            throw new InvalidArgumentException('No driver profile is linked to this user.');
        }

        if ((int) $sale->driver_id !== $driverId) {
            throw new InvalidArgumentException('Forbidden.');
        }
    }

    private function tenantOwnsProduct(int $tenantId, int $productId): bool
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($productId)
            ->exists();
    }

    private function tenantOwnsCustomer(int $tenantId, int $customerId): bool
    {
        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($customerId)
            ->exists();
    }
}
