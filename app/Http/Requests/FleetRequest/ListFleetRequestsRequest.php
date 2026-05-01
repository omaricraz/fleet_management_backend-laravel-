<?php

namespace App\Http\Requests\FleetRequest;

use App\Http\Requests\TenantScopedFormRequest;
use App\Services\RequestService;
use Illuminate\Validation\Rule;

class ListFleetRequestsRequest extends TenantScopedFormRequest
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
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    RequestService::STATUS_PENDING,
                    RequestService::STATUS_APPROVED,
                    RequestService::STATUS_REJECTED,
                ]),
            ],
            'type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    RequestService::TYPE_FUEL,
                    RequestService::TYPE_MAINTENANCE,
                    RequestService::TYPE_INVENTORY,
                ]),
            ],
            'driver_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('drivers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant)),
            ],
        ];
    }
}
