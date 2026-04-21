<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreZoneRequest;
use App\Http\Requests\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = Zone::query()->where('tenant_id', $request->attributes->get('tenant_id'));

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['city'],
            ['id', 'city', 'number_of_stores', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, ZoneResource::class));
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = Zone::query()->create([
            ...$request->validated(),
            'tenant_id' => (int) $request->attributes->get('tenant_id'),
        ]);

        return $this->successResponse('Created successfully', new ZoneResource($zone), 201);
    }

    public function show(Request $request, Zone $zone): JsonResponse
    {
        return $this->successResponse('Success', new ZoneResource($zone));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $zone->fill($request->validated());
        $zone->save();

        return $this->successResponse('Updated successfully', new ZoneResource($zone->fresh()));
    }

    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        $zone->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
