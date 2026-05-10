<?php

namespace App\Http\Requests\Trip;

use App\Http\Requests\TenantScopedFormRequest;
use App\Services\TripService;
use Illuminate\Validation\Rule;

class ListTripsRequest extends TenantScopedFormRequest
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
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    TripService::STATUS_ACTIVE,
                    TripService::STATUS_CLOSED,
                ]),
            ],
            'driver_id' => ['sometimes', 'nullable', 'integer'],
            'car_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
