<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class TenantScopedFormRequest extends FormRequest
{
    protected function tenantId(): int
    {
        return (int) request()->attributes->get('tenant_id');
    }
}
