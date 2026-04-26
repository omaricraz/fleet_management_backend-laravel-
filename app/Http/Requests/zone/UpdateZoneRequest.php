<?php

namespace App\Http\Requests\zone;
use App\Http\Requests\TenantScopedFormRequest;

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
