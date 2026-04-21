<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Requests\Platform\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTenantController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['name', 'subscription_plan'],
            ['id', 'name', 'subscription_plan', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, TenantResource::class));
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::query()->create($request->validated());

        return $this->successResponse('Created successfully', new TenantResource($tenant), 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return $this->successResponse('Success', new TenantResource($tenant));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->fill($request->validated());
        $tenant->save();

        return $this->successResponse('Updated successfully', new TenantResource($tenant->fresh()));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
