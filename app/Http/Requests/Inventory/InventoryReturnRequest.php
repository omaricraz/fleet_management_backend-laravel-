<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\TenantScopedFormRequest;
use Illuminate\Validation\Rule;

class InventoryReturnRequest extends TenantScopedFormRequest
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
            'notes' => ['required', 'string', 'min:1', 'max:2000'],
            'cars' => ['required', 'array', 'min:1'],
            'cars.*.car_id' => [
                'required', 'integer',
                Rule::exists('cars', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'cars.*.trip_id' => [
                'nullable', 'integer',
                Rule::exists('trips', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'cars.*.items' => ['required', 'array', 'min:1'],
            'cars.*.items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'cars.*.items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
