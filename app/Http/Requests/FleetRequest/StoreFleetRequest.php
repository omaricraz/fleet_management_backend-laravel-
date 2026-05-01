<?php

namespace App\Http\Requests\FleetRequest;

use App\Http\Requests\TenantScopedFormRequest;
use App\Services\RequestService;
use Illuminate\Validation\Rule;

class StoreFleetRequest extends TenantScopedFormRequest
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
            'type' => [
                'required',
                'string',
                Rule::in([
                    RequestService::TYPE_FUEL,
                    RequestService::TYPE_MAINTENANCE,
                    RequestService::TYPE_INVENTORY,
                ]),
            ],
            'fuel_requested' => [
                Rule::requiredIf(fn () => $this->input('type') === RequestService::TYPE_FUEL),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'litre_cost' => [
                Rule::requiredIf(fn () => $this->input('type') === RequestService::TYPE_FUEL),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'maintenance_requested' => [
                Rule::requiredIf(fn () => $this->input('type') === RequestService::TYPE_MAINTENANCE),
                'nullable',
                'string',
                'max:500',
            ],
            'notes' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
