<?php

namespace App\Http\Requests\customer;
use App\Http\Requests\TenantScopedFormRequest;

use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends TenantScopedFormRequest
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
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:255'],
            'zone_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('zones', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())),
            ],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
