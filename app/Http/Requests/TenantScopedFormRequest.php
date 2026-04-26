<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
//this is a base request class for all requests that need to be scoped to a tenant
abstract class TenantScopedFormRequest extends FormRequest
{
    protected function tenantId(): int
    {
        return (int) request()->attributes->get('tenant_id');
    }
}
