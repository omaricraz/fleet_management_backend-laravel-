<?php

namespace App\Http\Requests;

class StoreZoneRequest extends TenantScopedFormRequest
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
            'city' => ['required', 'string', 'max:255'],
            'number_of_stores' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
