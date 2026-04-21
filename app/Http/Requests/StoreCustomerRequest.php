<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreCustomerRequest extends TenantScopedFormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zones', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
