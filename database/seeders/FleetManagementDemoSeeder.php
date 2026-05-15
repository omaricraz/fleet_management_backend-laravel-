<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class FleetManagementDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Optional truncate
        DB::table('trip_events')->truncate();
        DB::table('inventory_transaction')->truncate();
        DB::table('sales')->truncate();
        DB::table('inventory')->truncate();
        DB::table('fuel')->truncate();
        DB::table('maintenance')->truncate();
        DB::table('location')->truncate();
        DB::table('requests')->truncate();
        DB::table('customers')->truncate();
        DB::table('trips')->truncate();
        DB::table('drivers')->truncate();
        DB::table('cars')->truncate();
        DB::table('products')->truncate();
        DB::table('zones')->truncate();
        DB::table('users')->truncate();
        DB::table('settings')->truncate();
        DB::table('tenant')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // =========================================================
        // TENANTS
        // =========================================================
        $tenantIds = [];

        for ($i = 1; $i <= 5; $i++) {
            $tenantIds[] = DB::table('tenant')->insertGetId([
                'name' => 'Fleet Company ' . $i,
                'subscription_plan' => ['basic', 'premium', 'enterprise'][rand(0, 2)],
                'logo' => 'logos/company_' . $i . '.png',
                'main_color' => '1E40AF',
                'bg_color' => 'F3F4F6',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // =========================================================
        // USERS
        // =========================================================
        $userIds = [];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 20; $i++) {
                $role = ['admin', 'manager', 'driver'][rand(0, 2)];

                $userIds[] = DB::table('users')->insertGetId([
                    'name' => 'User ' . $tenantId . '-' . $i,
                    'email' => 'user' . $tenantId . '_' . $i . '@fleet.com',
                    'password' => Hash::make('password'),
                    'tenant_id' => $tenantId,
                    'role' => $role,
                    'is_platform_admin' => $i === 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // SETTINGS
        // =========================================================
        foreach ($tenantIds as $tenantId) {
            DB::table('settings')->insert([
                [
                    'key_name' => 'currency',
                    'value' => 'USD',
                    'type' => 'string',
                    'scope' => 'global',
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key_name' => 'sms_enabled',
                    'value' => 'true',
                    'type' => 'boolean',
                    'scope' => 'global',
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // =========================================================
        // ZONES
        // =========================================================
        $zoneIds = [];
        $cities = ['Hargeisa', 'Berbera', 'Borama', 'Burco', 'Gabiley'];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 10; $i++) {
                $zoneIds[] = DB::table('zones')->insertGetId([
                    'number_of_stores' => rand(10, 100),
                    'name' => 'Zone ' . $i,
                    'city' => $cities[array_rand($cities)],
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // PRODUCTS
        // =========================================================
        $productIds = [];

        $productNames = [
            'Water',
            'Juice',
            'Milk',
            'Sugar',
            'Rice',
            'Pasta',
            'Soap',
            'Oil',
            'Flour',
            'Salt',
            'Tea',
            'Coffee'
        ];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 25; $i++) {
                $productIds[] = DB::table('products')->insertGetId([
                    'item' => $productNames[array_rand($productNames)] . ' ' . $i,
                    'type' => 'retail',
                    'price' => rand(1, 100),
                    'unit_volume' => rand(1, 10),
                    'unit_weight' => rand(1, 20),
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // CARS
        // =========================================================
        $carIds = [];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 15; $i++) {
                $carIds[] = DB::table('cars')->insertGetId([
                    'model' => 'Toyota Canter ' . $i,
                    'plate_number' => 'SL-' . rand(1000, 9999),
                    'overall_volume_capacity' => rand(100, 500),
                    'overall_weight_capacity' => rand(1000, 5000),
                    'tenant_id' => $tenantId,
                    'fuel_efficiency' => rand(5, 20),
                    'color' => ['Red', 'Blue', 'White', 'Black'][rand(0, 3)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // DRIVERS
        // =========================================================
        $driverIds = [];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 20; $i++) {
                $driverIds[] = DB::table('drivers')->insertGetId([
                    'full_name' => 'Driver ' . $tenantId . '-' . $i,
                    'phone' => '25263' . rand(1000000, 9999999),
                    'tenant_id' => $tenantId,
                    'zone_id' => $zoneIds[array_rand($zoneIds)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // TRIPS
        // =========================================================
        $tripIds = [];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 50; $i++) {
                $start = Carbon::now()->subDays(rand(1, 60));
                $end = (clone $start)->addHours(rand(4, 24));

                $tripIds[] = DB::table('trips')->insertGetId([
                    'start_date' => $start,
                    'end_date' => rand(0, 1) ? $end : null,
                    'arrival_time' => '08:00:00',
                    'departure' => '09:00:00',
                    'volume_capacity' => rand(100, 300),
                    'weight_capacity' => rand(500, 3000),
                    'distance_covered' => rand(10, 500),
                    'destination' => $cities[array_rand($cities)],
                    'status' => rand(0, 1) ? 'active' : 'closed',
                    'zone_id' => $zoneIds[array_rand($zoneIds)],
                    'driver_id' => $driverIds[array_rand($driverIds)],
                    'car_id' => $carIds[array_rand($carIds)],
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // CUSTOMERS
        // =========================================================
        $customerIds = [];

        foreach ($tenantIds as $tenantId) {
            for ($i = 1; $i <= 50; $i++) {
                $customerIds[] = DB::table('customers')->insertGetId([
                    'full_name' => 'Store Customer ' . $i,
                    'phone' => '25265' . rand(1000000, 9999999),
                    'zone_id' => $zoneIds[array_rand($zoneIds)],
                    'tenant_id' => $tenantId,
                    'latitude' => 9.5600 + rand(-1000, 1000) / 10000,
                    'longitude' => 44.0650 + rand(-1000, 1000) / 10000,
                    'trip_id' => $tripIds[array_rand($tripIds)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // =========================================================
        // INVENTORY
        // =========================================================
        for ($i = 1; $i <= 150; $i++) {
            DB::table('inventory')->insert([
                'quantity' => rand(10, 500),
                'product_id' => $productIds[array_rand($productIds)],
                'car_id' => $carIds[array_rand($carIds)],
                'trip_id' => $tripIds[array_rand($tripIds)],
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // =========================================================
        // SALES
        // =========================================================
        $saleIds = [];

        for ($i = 1; $i <= 200; $i++) {
            $quantity = rand(1, 20);
            $price = rand(5, 100);

            $saleIds[] = DB::table('sales')->insertGetId([
                'quantity' => $quantity,
                'total_price' => $quantity * $price,
                'trip_id' => $tripIds[array_rand($tripIds)],
                'driver_id' => $driverIds[array_rand($driverIds)],
                'customer_id' => $customerIds[array_rand($customerIds)],
                'product_id' => $productIds[array_rand($productIds)],
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // =========================================================
        // INVENTORY TRANSACTIONS
        // =========================================================
        $types = ['opening', 'load', 'sale', 'return', 'adjustment', 'closing'];

        for ($i = 1; $i <= 250; $i++) {
            $before = rand(10, 200);
            $change = rand(1, 50);
            $after = $before - $change;

            DB::table('inventory_transaction')->insert([
                'car_id' => $carIds[array_rand($carIds)],
                'product_id' => $productIds[array_rand($productIds)],
                'quantity' => $change,
                'type' => $types[array_rand($types)],
                'trip_id' => $tripIds[array_rand($tripIds)],
                'sale_id' => $saleIds[array_rand($saleIds)],
                'created_at' => now(),
                'notes' => 'Auto generated transaction',
                'actual_quantity' => $after,
                'expected_quantity' => $before,
                'variance' => $after - $before,
                'user_id' => $userIds[array_rand($userIds)],
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'before_qty' => $before,
                'after_qty' => $after,
            ]);
        }

        // =========================================================
        // FUEL
        // =========================================================
        for ($i = 1; $i <= 100; $i++) {
            DB::table('fuel')->insert([
                'current_fuel' => rand(10, 200),
                'Refill_date' => now(),
                'fuel_price_per_l' => rand(1, 3),
                'cost' => rand(20, 500),
                'status' => ['pending', 'accepted', 'rejected'][rand(0, 2)],
                'trip_id' => $tripIds[array_rand($tripIds)],
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'car_id' => $carIds[array_rand($carIds)],
                'driver_id' => $driverIds[array_rand($driverIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // =========================================================
        // MAINTENANCE
        // =========================================================
        for ($i = 1; $i <= 100; $i++) {
            DB::table('maintenance')->insert([
                'status' => ['pending', 'accepted', 'rejected', 'completed'][rand(0, 3)],
                'service' => 'Oil Change',
                'garage' => 'Garage ' . rand(1, 10),
                'cost' => rand(50, 1000),
                'service_start_date' => now()->subDays(rand(1, 20)),
                'service_end_date' => now(),
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'car_id' => $carIds[array_rand($carIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // =========================================================
        // LOCATIONS
        // =========================================================
        for ($i = 1; $i <= 300; $i++) {
            DB::table('location')->insert([
                'latitude' => 9.5600 + rand(-1000, 1000) / 10000,
                'longitude' => 44.0650 + rand(-1000, 1000) / 10000,
                'speed' => rand(20, 120),
                'heading' => rand(0, 360),
                'accuracy' => rand(1, 10),
                'recorded_at' => now(),
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'trip_id' => $tripIds[array_rand($tripIds)],
                'car_id' => $carIds[array_rand($carIds)],
                'driver_id' => $driverIds[array_rand($driverIds)],
            ]);
        }

        // =========================================================
        // REQUESTS
        // =========================================================
        $requestTypes = ['fuel', 'maintenance', 'inventory'];

        for ($i = 1; $i <= 100; $i++) {
            DB::table('requests')->insert([
                'type' => $requestTypes[array_rand($requestTypes)],
                'driver_id' => $driverIds[array_rand($driverIds)],
                'user_id' => $userIds[array_rand($userIds)],
                'status' => ['pending', 'approved', 'rejected'][rand(0, 2)],
                'notes' => 'Generated request note',
                'maintenance_requested' => 'Brake Repair',
                'fuel_requested' => rand(10, 100),
                'cost' => rand(20, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // =========================================================
        // TRIP EVENTS
        // =========================================================
        for ($i = 1; $i <= 150; $i++) {
            DB::table('trip_events')->insert([
                'trip_id' => $tripIds[array_rand($tripIds)],
                'user_id' => $userIds[array_rand($userIds)],
                'tenant_id' => $tenantIds[array_rand($tenantIds)],
                'event_type' => ['active', 'closed'][rand(0, 1)],
                'quantity' => rand(1, 50),
                'amount' => rand(20, 1000),
                'product_id' => $productIds[array_rand($productIds)],
                'metadata' => json_encode([
                    'source' => 'system',
                    'generated' => true,
                ]),
                'description' => 'Auto generated trip event',
                'created_at' => now(),
            ]);
        }

        echo "Fleet Management Demo Seeder Completed Successfully\n";
    }
}
