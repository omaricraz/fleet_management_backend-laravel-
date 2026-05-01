<?php

namespace App\Http\Requests\FleetRequest;

use App\Http\Requests\TenantScopedFormRequest;

class RejectFleetRequest extends TenantScopedFormRequest
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
            'notes' => ['required', 'string', 'max:20000'],
        ];
    }
}
