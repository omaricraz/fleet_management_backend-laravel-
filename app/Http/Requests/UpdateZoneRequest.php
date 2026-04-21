<?php

namespace App\Http\Requests;

class UpdateZoneRequest extends TenantScopedFormRequest
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
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'number_of_stores' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
