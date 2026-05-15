<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = [];

        for ($i = 1; $i <= 40; $i++) {

            $drivers[] = [
                'full_name' => fake()->name(),

                'phone' => '63' . rand(1000000, 9999999),

                'tenant_id' => 1,

                'zone_id' => rand(10, 20),

                'user_id' => null,

                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }

        DB::table('drivers')->insert($drivers);
    }
}
