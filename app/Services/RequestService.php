<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\FleetRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RequestService
{
    public const TYPE_FUEL = 'fuel';

    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_INVENTORY = 'inventory';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @param  array<string, mixed>  $data  validated payload
     */
    public function createRequest(int $tenantId, User $user, Driver $driver, array $data): FleetRequest
    {
        $type = (string) $data['type'];

        $attributes = [
            'type' => $type,
            'driver_id' => $driver->id,
            'user_id' => $user->id,
            'status' => self::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'maintenance_requested' => null,
            'fuel_requested' => null,
            'litre_cost' => null,
        ];

        if ($type === self::TYPE_FUEL) {
            $attributes['fuel_requested'] = $data['fuel_requested'];
            $attributes['litre_cost'] = $data['litre_cost'];
        } elseif ($type === self::TYPE_MAINTENANCE) {
            $attributes['maintenance_requested'] = $data['maintenance_requested'];
        }

        $request = FleetRequest::query()->create($attributes);

        Log::info('fleet_request.created', [
            'tenant_id' => $tenantId,
            'fleet_request_id' => $request->id,
            'driver_id' => $driver->id,
            'user_id' => $user->id,
            'type' => $type,
        ]);

        return $request->fresh();
    }

    public function approveRequest(int $tenantId, FleetRequest $fleetRequest, User $actor): FleetRequest
    {
        if ($fleetRequest->status !== self::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending requests can be approved.');
        }

        return DB::transaction(function () use ($tenantId, $fleetRequest, $actor) {
            $fleetRequest->status = self::STATUS_APPROVED;
            $fleetRequest->save();

            $context = [
                'tenant_id' => $tenantId,
                'fleet_request_id' => $fleetRequest->id,
                'type' => $fleetRequest->type,
                'actor_user_id' => $actor->id,
            ];

            if ($fleetRequest->type === self::TYPE_FUEL
                && $fleetRequest->fuel_requested !== null
                && $fleetRequest->litre_cost !== null) {
                $context['estimated_total_cost'] = round(
                    (float) $fleetRequest->fuel_requested * (float) $fleetRequest->litre_cost,
                    5
                );
            }

            Log::info('fleet_request.approved', $context);

            return $fleetRequest->fresh();
        });
    }

    /**
     * @param  array{notes?: string|null}  $data
     */
    public function rejectRequest(int $tenantId, FleetRequest $fleetRequest, User $actor, array $data): FleetRequest
    {
        if ($fleetRequest->status !== self::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending requests can be rejected.');
        }

        return DB::transaction(function () use ($tenantId, $fleetRequest, $actor, $data) {
            $fleetRequest->status = self::STATUS_REJECTED;

            $rejectionNote = isset($data['notes']) ? trim((string) $data['notes']) : '';
            if ($rejectionNote !== '') {
                $existing = $fleetRequest->notes !== null ? trim((string) $fleetRequest->notes) : '';
                $fleetRequest->notes = $existing === ''
                    ? 'Rejected: '.$rejectionNote
                    : $existing."\n\nRejected: ".$rejectionNote;
            }

            $fleetRequest->save();

            Log::info('fleet_request.rejected', [
                'tenant_id' => $tenantId,
                'fleet_request_id' => $fleetRequest->id,
                'type' => $fleetRequest->type,
                'actor_user_id' => $actor->id,
            ]);

            return $fleetRequest->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $filters  validated query params
     * @return Collection<int, FleetRequest>
     */
    public function getRequests(int $tenantId, array $filters): Collection
    {
        $query = FleetRequest::query()
            ->forTenant($tenantId)
            ->with(['driver', 'user'])
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', (int) $filters['driver_id']);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, FleetRequest>
     */
    public function getDriverRequests(int $tenantId, int $driverId): Collection
    {
        return FleetRequest::query()
            ->forTenant($tenantId)
            ->where('driver_id', $driverId)
            ->with(['driver', 'user'])
            ->orderByDesc('id')
            ->get();
    }

    public function findForTenant(int $tenantId, int $id): ?FleetRequest
    {
        return FleetRequest::query()
            ->forTenant($tenantId)
            ->whereKey($id)
            ->first();
    }

    public function deleteRequest(int $tenantId, FleetRequest $fleetRequest): void
    {
        Log::info('fleet_request.deleted', [
            'tenant_id' => $tenantId,
            'fleet_request_id' => $fleetRequest->id,
            'type' => $fleetRequest->type,
            'prior_status' => $fleetRequest->status,
        ]);

        $fleetRequest->delete();
    }
}
