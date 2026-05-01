<?php

namespace App\Http\Requests\driver;
use App\Http\Requests\TenantScopedFormRequest;

use Illuminate\Validation\Rule;

class UpdateDriverRequest extends TenantScopedFormRequest
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
        ];
    }
}
