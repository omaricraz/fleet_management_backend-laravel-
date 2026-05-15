<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [];

        for ($i = 1; $i <= 100; $i++) {

            $customers[] = [
                'full_name' => fake()->name(),

                'phone' => '61' . rand(1000000, 9999999),

                'zone_id' => rand(10, 20),

                'tenant_id' => 1,

                'latitude' => fake()->latitude(2, 12),

                'longitude' => fake()->longitude(42, 52),

                'trip_id' => rand(101, 200),

                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }

        DB::table('customers')->insert($customers);
    }
}
