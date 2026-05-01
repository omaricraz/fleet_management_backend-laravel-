<?php

namespace App\Http\Requests\Sale;

use App\Http\Requests\TenantScopedFormRequest;

class UpdateSaleRequest extends TenantScopedFormRequest
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
            'total_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
