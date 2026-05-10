<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\User;

class UserObserver
{
    public function saved(User $user): void
    {
        if ($user->role === 'driver' && $user->tenant_id !== null) {
            $driver = Driver::withTrashed()->firstOrNew(['user_id' => $user->id]);
            if ($driver->trashed()) {
                $driver->restore();
            }
            $driver->fill([
                'tenant_id' => $user->tenant_id,
                'full_name' => $user->name,
            ]);
            if (! $driver->exists) {
                $driver->phone = 'n/a-'.$user->id;
            }
            $driver->save();

            return;
        }

        Driver::query()->where('user_id', $user->id)->update(['user_id' => null]);
    }
}
