<?php

namespace App\Http\Requests\car;

use Illuminate\Validation\Rule;
use App\Http\Requests\TenantScopedFormRequest;

class UpdateCarRequest extends TenantScopedFormRequest
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
        $car = $this->route('car');

        return [
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'plate_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('cars', 'plate_number')
                    ->where(fn ($q) => $q->where('tenant_id', $this->tenantId()))
                    ->ignore($car),
            ],
            'overall_volume_capacity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'overall_weight_capacity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'current_fuel' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fuel_efficiency' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'color' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
