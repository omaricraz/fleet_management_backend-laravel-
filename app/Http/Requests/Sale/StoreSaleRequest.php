<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\TenantScopedFormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends TenantScopedFormRequest
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
        $tenantId = $this->tenantId();

        return [
            'trip_id' => [
                'required',
                'integer',
                Rule::exists('trips', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'total_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
