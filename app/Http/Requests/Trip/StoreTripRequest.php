<?php

namespace App\Http\Requests\Trip;

use App\Http\Requests\TenantScopedFormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends TenantScopedFormRequest
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
        $tenant = $this->tenantId();

        return [
            'driver_id' => [
                'required',
                'integer',
                Rule::exists('drivers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zones', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
            'destination' => ['nullable', 'string', 'max:500'],
        ];
    }
}
