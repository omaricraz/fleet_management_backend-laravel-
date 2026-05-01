<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Driver;
use App\Models\User;

trait ResolvesDriverForAuthenticatedUser
{
    private function resolveDriverForAuthenticatedUser(User $user, int $tenantId): ?Driver
    {
        $byId = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->id)
            ->first();

        if ($byId !== null) {
            return $byId;
        }

        return Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('full_name', $user->name)
            ->first();
    }
}
