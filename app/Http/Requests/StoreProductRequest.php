<?php

namespace App\Http\Requests;

class StoreProductRequest extends TenantScopedFormRequest
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
            'item' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'unit_volume' => ['nullable', 'numeric', 'min:0'],
            'unit_weight' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
