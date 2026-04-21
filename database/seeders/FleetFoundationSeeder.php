<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FleetFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Zamzam Group',
            'subscription_plan' => 'standard',
        ]);

        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => 'manager',
        ]);

        User::query()->create([
            'name' => 'Driver',
            'email' => 'driver@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => 'driver',
        ]);

        User::query()->forceCreate([
            'name' => 'Platform Admin',
            'email' => 'platform@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => null,
            'role' => 'admin',
            'is_platform_admin' => true,
        ]);
    }
}
