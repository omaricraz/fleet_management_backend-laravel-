<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\product\StoreProductRequest;
use App\Http\Requests\product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->where('tenant_id', $request->attributes->get('tenant_id'));

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['item', 'type'],
            ['id', 'item', 'type', 'price', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, ProductResource::class));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create([
            ...$request->validated(),
            'tenant_id' => (int) $request->attributes->get('tenant_id'),
        ]);

        return $this->successResponse('Created successfully', new ProductResource($product), 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        return $this->successResponse('Success', new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->fill($request->validated());
        $product->save();

        return $this->successResponse('Updated successfully', new ProductResource($product->fresh()));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $product->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
