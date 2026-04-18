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
    }
}
