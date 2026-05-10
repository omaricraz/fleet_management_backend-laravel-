<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Driver;
use App\Models\User;

trait ResolvesDriverForAuthenticatedUser
{
    private function resolveDriverForAuthenticatedUser(User $user, int $tenantId): ?Driver
    {
        $byUserId = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if ($byUserId !== null) {
            return $byUserId;
        }

        $byLegacyId = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $user->id)
            ->first();

        if ($byLegacyId !== null) {
            return $byLegacyId;
        }

        return Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('full_name', $user->name)
            ->first();
    }
}
