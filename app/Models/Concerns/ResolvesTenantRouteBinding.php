<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait ResolvesTenantRouteBinding
{
    public function resolveRouteBinding($value, $field = null)
    {
        $tenantId = request()->attributes->get('tenant_id');

        if ($tenantId === null) {
            abort(404);
        }

        $field ??= $this->getRouteKeyName();

        $model = static::query()
            ->where('tenant_id', $tenantId)
            ->where($field, $value)
            ->first();

        return $model ?? abort(404);
    }
}
