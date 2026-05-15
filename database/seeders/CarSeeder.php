<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CarSeeder extends Seeder
{
    public function run(): void
    {
        $cars = [];

        $models = [
            'Toyota Dyna',
            'Isuzu NPR',
            'Mitsubishi Fuso',
            'Hino 300',
            'Toyota Hilux',
            'Nissan Atlas',
            'Mercedes Actros',
            'Volvo FH',
        ];

        $colors = [
            'White',
            'Black',
            'Blue',
            'Silver',
            'Red',
        ];

        for ($i = 1; $i <= 30; $i++) {

            $cars[] = [
                'model' => fake()->randomElement($models),

                'plate_number' => 'SO-' . rand(1000, 9999),

                'overall_volume_capacity' => rand(50, 500),

                'overall_weight_capacity' => rand(1000, 30000),

                'tenant_id' => 1,

                'fuel_efficiency' => rand(4, 15),

                'color' => fake()->randomElement($colors),

                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }

        DB::table('cars')->insert($cars);
    }
}
