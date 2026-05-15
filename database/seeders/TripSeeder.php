<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TripSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        $trips = [];

        for ($i = 1; $i <= 100; $i++) {

            $startDate = Carbon::now()->subDays(rand(1, 180));
            $endDate = (clone $startDate)->addHours(rand(4, 72));

            $departureTime = Carbon::createFromTime(rand(0, 23), rand(0, 59), 0);
            $arrivalTime = (clone $departureTime)->addHours(rand(2, 20));

            $trips[] = [
                'start_date' => $startDate,
                'end_date' => $endDate,

                'arrival_time' => $arrivalTime->format('H:i:s'),
                'departure' => $departureTime->format('H:i:s'),

                'volume_capacity' => rand(10, 100),
                'weight_capacity' => rand(500, 10000),

                'distance_covered' => rand(50, 3000),

                'destination' => $faker->city() . ', ' . $faker->country(),

                'status' => $faker->randomElement([
                    'active',
                    'closed'
                ]),

                // Make sure these IDs exist in your DB
                'zone_id' => rand(10, 20),
                'driver_id' => rand(124, 160),
                'car_id' => rand(10, 30),
                'tenant_id' => 1,

                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }

        DB::table('trips')->insert($trips);
    }
}
