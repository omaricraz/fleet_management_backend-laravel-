<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\TenantScopedFormRequest;
use Illuminate\Validation\Rule;

class InventoryClosingCountRequest extends TenantScopedFormRequest
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
            'trip_id' => [
                'nullable', 'integer',
                Rule::exists('trips', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'car_id' => [
                'required', 'integer',
                Rule::exists('cars', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'items.*.actual_quantity' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
