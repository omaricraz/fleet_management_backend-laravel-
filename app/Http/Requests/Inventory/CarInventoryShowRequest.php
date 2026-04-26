<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\TenantScopedFormRequest;

class CarInventoryShowRequest extends TenantScopedFormRequest
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
