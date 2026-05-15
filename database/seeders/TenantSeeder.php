<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Zamzam Group',
                'subscription_plan' => 'standard',
                'logo' => 'logos/zamzam.png',
                'main_color' => '2563EB',
                'bg_color' => 'F8FAFC',
            ],
            [
                'name' => 'Hormuud Logistics',
                'subscription_plan' => 'premium',
                'logo' => 'logos/hormuud.png',
                'main_color' => '16A34A',
                'bg_color' => 'ECFDF5',
            ],
            [
                'name' => 'Sahal Transport',
                'subscription_plan' => 'enterprise',
                'logo' => 'logos/sahal.png',
                'main_color' => 'DC2626',
                'bg_color' => 'FEF2F2',
            ],
        ];

        foreach ($tenants as $tenant) {
            Tenant::query()->create($tenant);
        }
    }
}
