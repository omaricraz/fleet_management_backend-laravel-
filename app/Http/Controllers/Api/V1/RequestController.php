<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDriverForAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\FleetRequest\ListFleetRequestsRequest;
use App\Http\Requests\FleetRequest\RejectFleetRequest;
use App\Http\Requests\FleetRequest\StoreFleetRequest;
use App\Models\FleetRequest;
use App\Models\User;
use App\Services\RequestService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RequestController extends Controller
{
    use ApiResponse;
    use ResolvesDriverForAuthenticatedUser;

    public function __construct(
        private readonly RequestService $requests,
    ) {}

    public function store(StoreFleetRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        $driver = $this->resolveDriverForAuthenticatedUser($user, $tenantId);
        if ($driver === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }

        $fleetRequest = $this->requests->createRequest($tenantId, $user, $driver, $request->validated());

        return $this->successResponse('Request created successfully', $fleetRequest->toArray(), 201);
    }

    public function index(ListFleetRequestsRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');

        $data = $this->requests->getRequests($tenantId, $request->validated())
            ->map(fn (FleetRequest $r) => $r->toArray())
            ->values()
            ->all();

        return $this->successResponse('Success', $data);
    }

    public function my(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        $driver = $this->resolveDriverForAuthenticatedUser($user, $tenantId);
        if ($driver === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }

        $data = $this->requests->getDriverRequests($tenantId, (int) $driver->id)
            ->map(fn (FleetRequest $r) => $r->toArray())
            ->values()
            ->all();

        return $this->successResponse('Success', $data);
    }

    public function show(Request $request, FleetRequest $fleetRequest): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureFleetRequestVisible($user, $tenantId, $fleetRequest)) {
            return $response;
        }

        $fleetRequest->load(['driver', 'user']);

        $payload = $fleetRequest->toArray();
        if ($fleetRequest->type === RequestService::TYPE_FUEL
            && $fleetRequest->fuel_requested !== null
            && $fleetRequest->litre_cost !== null) {
            $payload['estimated_total_cost'] = round(
                (float) $fleetRequest->fuel_requested * (float) $fleetRequest->litre_cost,
                5
            );
        }

        return $this->successResponse('Success', $payload);
    }

    public function approve(Request $request, FleetRequest $fleetRequest): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->requests->approveRequest($tenantId, $fleetRequest, $user);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        $payload = $updated->toArray();
        if ($updated->type === RequestService::TYPE_FUEL
            && $updated->fuel_requested !== null
            && $updated->litre_cost !== null) {
            $payload['estimated_total_cost'] = round(
                (float) $updated->fuel_requested * (float) $updated->litre_cost,
                5
            );
        }

        return $this->successResponse('Request approved successfully', $payload);
    }

    public function reject(RejectFleetRequest $request, FleetRequest $fleetRequest): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->requests->rejectRequest($tenantId, $fleetRequest, $user, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Request rejected successfully', $updated->toArray());
    }

    public function destroy(Request $request, FleetRequest $fleetRequest): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');

        $this->requests->deleteRequest($tenantId, $fleetRequest);

        return $this->successResponse('Request deleted successfully', (object) []);
    }

    private function ensureFleetRequestVisible(User $user, int $tenantId, FleetRequest $fleetRequest): ?JsonResponse
    {
        if (! in_array($user->role, ['admin', 'manager', 'driver'], true)) {
            return $this->errorResponse('Forbidden', (object) [], 403);
        }

        if (in_array($user->role, ['admin', 'manager'], true)) {
            return null;
        }

        $driver = $this->resolveDriverForAuthenticatedUser($user, $tenantId);
        if ($driver === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }
        if ((int) $fleetRequest->driver_id !== (int) $driver->id) {
            return $this->errorResponse('Forbidden', (object) [], 403);
        }

        return null;
    }
}
