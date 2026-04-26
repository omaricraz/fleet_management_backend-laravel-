<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarResource;
use App\Models\Driver;
use App\Models\User;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverInventoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        $driver = $this->resolveDriverForUser($user, $tenantId);

        if ($driver === null) {
            return $this->errorResponse(
                'No driver profile is linked to this user. Match your account name to a driver or contact an administrator.',
                (object) [],
                404
            );
        }

        $data = $this->inventory->getDriverInventory($tenantId, (int) $driver->id);

        $car = $data['car'];

        return $this->successResponse('Success', [
            'car' => $car ? new CarResource($car) : null,
            'snapshot' => $data['snapshot'],
            'transactions' => $data['transactions'],
        ]);
    }

    private function resolveDriverForUser(User $user, int $tenantId): ?Driver
    {
        $byId = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->id)
            ->first();

        if ($byId) {
            return $byId;
        }

        return Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('full_name', $user->name)
            ->first();
    }
}
