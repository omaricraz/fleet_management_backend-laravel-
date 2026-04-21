<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = Driver::query()
            ->with('zone')
            ->where('tenant_id', $request->attributes->get('tenant_id'));

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['full_name', 'phone'],
            ['id', 'full_name', 'phone', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, DriverResource::class));
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $driver = Driver::query()->create([
            ...$request->validated(),
            'tenant_id' => (int) $request->attributes->get('tenant_id'),
        ]);
        $driver->load('zone');

        return $this->successResponse('Created successfully', new DriverResource($driver), 201);
    }

    public function show(Request $request, Driver $driver): JsonResponse
    {
        $driver->load('zone');

        return $this->successResponse('Success', new DriverResource($driver));
    }

    public function update(UpdateDriverRequest $request, Driver $driver): JsonResponse
    {
        $driver->fill($request->validated());
        $driver->save();

        return $this->successResponse('Updated successfully', new DriverResource($driver->fresh()->load('zone')));
    }

    public function destroy(Request $request, Driver $driver): JsonResponse
    {
        $driver->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
