<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // remove existing data
        DB::table('inventory')->truncate();

        $inventory = [];

        $usedCombinations = [];

        while (count($inventory) < 200) {

            $carId = rand(10, 30);

            $productId = rand(1, 10);

            $tenantId = 1;

            // UNIQUE INDEX:
            // car_id + product_id + tenant_id
            $combinationKey =
                $carId . '-' .
                $productId . '-' .
                $tenantId;

            // prevent duplicates
            if (isset($usedCombinations[$combinationKey])) {
                continue;
            }

            $usedCombinations[$combinationKey] = true;

            $inventory[] = [

                'quantity' => rand(1, 500),

                'product_id' => $productId,

                'car_id' => $carId,

                'trip_id' => rand(101, 200),

                'tenant_id' => $tenantId,

                'created_at' => now(),

                'updated_at' => now(),

                'deleted_at' => null,
            ];
        }

        DB::table('inventory')->insert($inventory);
    }
}
