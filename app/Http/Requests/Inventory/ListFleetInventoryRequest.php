<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\TenantScopedFormRequest;
use Illuminate\Validation\Rule;

class ListFleetInventoryRequest extends TenantScopedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenant = $this->tenantId();

        return [
            'car_id' => [
                'sometimes', 'integer',
                Rule::exists('cars', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'product_id' => [
                'sometimes', 'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'low_stock' => ['sometimes', 'in:1,0'],
            'search' => ['nullable', 'string', 'max:500'],
        ];
    }
}
