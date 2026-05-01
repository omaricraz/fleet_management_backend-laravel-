<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\TenantScopedFormRequest;

class ListSalesRequest extends TenantScopedFormRequest
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
        return [
            'trip_id' => ['sometimes', 'nullable', 'integer'],
            'driver_id' => ['sometimes', 'nullable', 'integer'],
            'product_id' => ['sometimes', 'nullable', 'integer'],
            'customer_id' => ['sometimes', 'nullable', 'integer'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
