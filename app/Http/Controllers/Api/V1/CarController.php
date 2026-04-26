<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\car\StoreCarRequest;
use App\Http\Requests\car\UpdateCarRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = Car::query()->where('tenant_id', $request->attributes->get('tenant_id'));

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['model', 'plate_number', 'color'],
            ['id', 'model', 'plate_number', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, CarResource::class));
    }

    public function store(StoreCarRequest $request): JsonResponse
    {
        $car = Car::query()->create([
            ...$request->validated(),
            'tenant_id' => (int) $request->attributes->get('tenant_id'),
        ]);

        return $this->successResponse('Created successfully', new CarResource($car), 201);
    }

    public function show(Request $request, Car $car): JsonResponse
    {
        return $this->successResponse('Success', new CarResource($car));
    }

    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $car->fill($request->validated());
        $car->save();

        return $this->successResponse('Updated successfully', new CarResource($car->fresh()));
    }

    public function destroy(Request $request, Car $car): JsonResponse
    {
        $car->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
