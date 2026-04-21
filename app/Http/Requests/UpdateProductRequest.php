<?php

namespace App\Http\Requests;

class UpdateProductRequest extends TenantScopedFormRequest
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
            'item' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_volume' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
