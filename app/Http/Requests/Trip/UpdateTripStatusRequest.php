<?php

namespace App\Http\Requests\Trip;

use App\Http\Requests\TenantScopedFormRequest;
use App\Services\TripService;
use Illuminate\Validation\Rule;

class UpdateTripStatusRequest extends TenantScopedFormRequest
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
                'required',
                'string',
                Rule::in([
                    TripService::STATUS_READY,
                    TripService::STATUS_LOADING,
                    TripService::STATUS_IN_TRANSIT,
                    TripService::STATUS_SELLING,
                    TripService::STATUS_OFFLOADING,
                    // idle only via POST …/end
                ]),
            ],
        ];
    }
}
