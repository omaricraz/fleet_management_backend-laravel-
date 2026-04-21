<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreCarRequest extends TenantScopedFormRequest
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
            'model' => ['required', 'string', 'max:255'],
            'plate_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cars', 'plate_number')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())),
            ],
            'overall_volume_capacity' => ['nullable', 'numeric', 'min:0'],
            'overall_weight_capacity' => ['nullable', 'numeric', 'min:0'],
            'current_fuel' => ['nullable', 'numeric', 'min:0'],
            'fuel_efficiency' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:255'],
        ];
    }
}
