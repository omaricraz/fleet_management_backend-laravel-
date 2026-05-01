<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDriverForAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\ListTripsRequest;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripStatusRequest;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TripController extends Controller
{
    use ApiResponse;
    use ResolvesDriverForAuthenticatedUser;

    public function __construct(
        private readonly TripService $trips,
    ) {}

    public function index(ListTripsRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();
        $driverScopeId = $this->resolveDriverScopeIdForList($user, $tenantId);
        if ($user->role === 'driver' && $driverScopeId === null) {
            return $this->errorResponse(
                'No driver profile is linked to this user.',
                (object) [],
                403
            );
        }

        $data = $this->trips->listTrips($tenantId, $request->validated(), $driverScopeId)
            ->map(fn (Trip $trip) => $trip->toArray())
            ->values()
            ->all();

        return $this->successResponse('Success', $data);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'driver') {
            $driver = $this->resolveDriverForAuthenticatedUser($user, $tenantId);
            if ($driver === null) {
                return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
            }
            if ((int) $request->validated('driver_id') !== (int) $driver->id) {
                return $this->errorResponse('Forbidden', (object) [], 403);
            }
        }

        try {
            $trip = $this->trips->createTrip($tenantId, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Trip created successfully', $trip->toArray(), 201);
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        if ($response = $this->ensureTripAccessible($request, $trip)) {
            return $response;
        }

        try {
            $payload = $this->trips->getTripDetails($tenantId, $trip);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Success', $this->formatTripWorkspace($payload));
    }

    public function updateStatus(UpdateTripStatusRequest $request, Trip $trip): JsonResponse
    {
        if ($response = $this->ensureTripAccessible($request, $trip)) {
            return $response;
        }

        try {
            $updated = $this->trips->updateStatus(
                $trip,
                (string) $request->validated('status'),
                $request->user()?->id !== null ? (int) $request->user()->id : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Status updated successfully', $updated->toArray());
    }

    public function start(Request $request, Trip $trip): JsonResponse
    {
        if ($response = $this->ensureTripAccessible($request, $trip)) {
            return $response;
        }

        try {
            $updated = $this->trips->startTrip(
                $trip,
                $request->user()?->id !== null ? (int) $request->user()->id : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Trip started successfully', $updated->toArray());
    }

    public function depart(Request $request, Trip $trip): JsonResponse
    {
        if ($response = $this->ensureTripAccessible($request, $trip)) {
            return $response;
        }

        try {
            $updated = $this->trips->departTrip(
                $trip,
                $request->user()?->id !== null ? (int) $request->user()->id : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Trip departed successfully', $updated->toArray());
    }

    public function end(Request $request, Trip $trip): JsonResponse
    {
        if ($response = $this->ensureTripAccessible($request, $trip)) {
            return $response;
        }

        try {
            $updated = $this->trips->endTrip(
                $trip,
                $request->user()?->id !== null ? (int) $request->user()->id : null
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Trip ended successfully', $updated->toArray());
    }

    public function destroy(Request $request, Trip $trip): JsonResponse
    {
        if ($response = $this->ensureTripAccessible($request, $trip)) {
            return $response;
        }

        $trip->delete();

        return $this->successResponse('Trip deleted successfully', (object) []);
    }

    private function resolveDriverScopeIdForList(User $user, int $tenantId): ?int
    {
        if ($user->role !== 'driver') {
            return null;
        }

        $driver = $this->resolveDriverForAuthenticatedUser($user, $tenantId);

        return $driver !== null ? (int) $driver->id : null;
    }

    private function ensureTripAccessible(Request $request, Trip $trip): ?JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->role !== 'driver') {
            return null;
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        $driver = $this->resolveDriverForAuthenticatedUser($user, $tenantId);
        if ($driver === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }
        if ((int) $trip->driver_id !== (int) $driver->id) {
            return $this->errorResponse('Forbidden', (object) [], 403);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function formatTripWorkspace(array $payload): array
    {
        /** @var Trip $trip */
        $trip = $payload['trip'];
        $timeline = $payload['timeline']->map(fn ($e) => $e->toArray())->values()->all();

        return [
            'trip' => $trip->toArray(),
            'driver' => $payload['driver']?->toArray(),
            'car' => $payload['car']?->toArray(),
            'timeline' => $timeline,
            'inventory_summary' => $payload['inventory_summary'],
            'sales_summary' => $payload['sales_summary'],
        ];
    }
}
