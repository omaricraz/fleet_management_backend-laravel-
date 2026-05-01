<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\ListSalesRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Models\Sale;
use App\Models\User;
use App\Services\SalesService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SalesController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SalesService $sales,
    ) {}

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        try {
            $sale = $this->sales->createSale($tenantId, $user, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Sale recorded successfully', $sale->toArray(), 201);
    }

    public function index(ListSalesRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();
        $driverScopeId = $this->sales->resolveDriverScopeId($user, $tenantId);

        if ($user->role === 'driver' && $driverScopeId === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }

        $data = $this->sales->getSales($tenantId, $request->validated(), $driverScopeId)
            ->map(fn (Sale $sale) => $sale->toArray())
            ->values()
            ->all();

        return $this->successResponse('Success', $data);
    }

    public function my(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();
        $driverId = $this->sales->resolveDriverScopeId($user, $tenantId);

        if ($driverId === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }

        $data = $this->sales->getDriverSales($tenantId, $driverId)
            ->map(fn (Sale $sale) => $sale->toArray())
            ->values()
            ->all();

        return $this->successResponse('Success', $data);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        if ($response = $this->ensureSaleAccessible($request, $sale)) {
            return $response;
        }

        $sale->loadMissing(['trip', 'driver', 'customer', 'product']);

        return $this->successResponse('Success', $sale->toArray());
    }

    public function destroy(Request $request, Sale $sale): JsonResponse
    {
        if ($response = $this->ensureSaleAccessible($request, $sale)) {
            return $response;
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->sales->deleteSale($sale, $user, $tenantId);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Sale deleted successfully', (object) []);
    }

    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        if ($response = $this->ensureSaleAccessible($request, $sale)) {
            return $response;
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->sales->updateSale($sale, $request->validated(), $user, $tenantId);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Sale updated successfully', $updated->toArray());
    }

    private function ensureSaleAccessible(Request $request, Sale $sale): ?JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->role !== 'driver') {
            return null;
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        $driverId = $this->sales->resolveDriverScopeId($user, $tenantId);

        if ($driverId === null) {
            return $this->errorResponse('No driver profile is linked to this user.', (object) [], 403);
        }

        if ((int) $sale->driver_id !== $driverId) {
            return $this->errorResponse('Forbidden', (object) [], 403);
        }

        return null;
    }
}
