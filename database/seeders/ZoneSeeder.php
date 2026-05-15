<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Hodan',
                'city' => 'Mogadishu',
            ],
            [
                'name' => 'Waberi',
                'city' => 'Mogadishu',
            ],
            [
                'name' => 'Yaqshid',
                'city' => 'Mogadishu',
            ],
            [
                'name' => 'Karaan',
                'city' => 'Mogadishu',
            ],
            [
                'name' => 'Hamar Weyne',
                'city' => 'Mogadishu',
            ],
            [
                'name' => 'Daynile',
                'city' => 'Mogadishu',
            ],
            [
                'name' => 'Garowe Central',
                'city' => 'Garowe',
            ],
            [
                'name' => 'Waaberi',
                'city' => 'Hargeisa',
            ],
            [
                'name' => 'Jigjiga Yar',
                'city' => 'Hargeisa',
            ],
            [
                'name' => 'Ahmed Dhagax',
                'city' => 'Hargeisa',
            ],
            [
                'name' => 'New Hargeisa',
                'city' => 'Hargeisa',
            ],
            [
                'name' => 'Isha Boorama',
                'city' => 'Borama',
            ],
            [
                'name' => 'October',
                'city' => 'Borama',
            ],
            [
                'name' => 'Xaafada Horseed',
                'city' => 'Bosaso',
            ],
            [
                'name' => 'Bander Qasim',
                'city' => 'Bosaso',
            ],
            [
                'name' => 'Howlwadaag',
                'city' => 'Kismayo',
            ],
            [
                'name' => 'Farjano',
                'city' => 'Kismayo',
            ],
            [
                'name' => 'Buulo Xuubey',
                'city' => 'Baidoa',
            ],
            [
                'name' => 'Towfiiq',
                'city' => 'Galkayo',
            ],
            [
                'name' => 'Israac',
                'city' => 'Galkayo',
            ],
        ];

        $data = [];

        foreach ($zones as $zone) {
            $data[] = [
                'number_of_stores' => rand(10, 200),
                'name' => $zone['name'],
                'city' => $zone['city'],
                'tenant_id' => 1,

                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }

        DB::table('zones')->insert($data);
    }
}
