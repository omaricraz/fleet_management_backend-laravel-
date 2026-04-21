<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->with('zone')
            ->where('tenant_id', $request->attributes->get('tenant_id'));

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['full_name', 'phone'],
            ['id', 'full_name', 'phone', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, CustomerResource::class));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create([
            ...$request->validated(),
            'tenant_id' => (int) $request->attributes->get('tenant_id'),
        ]);
        $customer->load('zone');

        return $this->successResponse('Created successfully', new CustomerResource($customer), 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $customer->load('zone');

        return $this->successResponse('Success', new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->fill($request->validated());
        $customer->save();

        return $this->successResponse('Updated successfully', new CustomerResource($customer->fresh()->load('zone')));
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
