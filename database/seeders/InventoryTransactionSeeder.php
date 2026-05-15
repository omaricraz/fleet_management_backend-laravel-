<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = [];

        $types = [
            'opening',
            'load',
            'sale',
            'return',
            'adjustment',
            'closing'
        ];

        for ($i = 1; $i <= 300; $i++) {

            $beforeQty = rand(0, 500);
            $changeQty = rand(1, 100);

            $type = $types[array_rand($types)];

            // simulate stock movement
            if (in_array($type, ['sale', 'closing'])) {
                $afterQty = max(0, $beforeQty - $changeQty);
            } else {
                $afterQty = $beforeQty + $changeQty;
            }

            $expectedQty = rand(0, 500);
            $actualQty = rand(0, 500);

            $transactions[] = [

                'car_id' => rand(10, 30),

                'product_id' => rand(1, 10),

                'quantity' => $changeQty,

                'type' => $type,

                'trip_id' => rand(101, 200),

                'sale_id' => rand(1500, 1800),

                'created_at' => now()->subDays(rand(0, 30)),

                'notes' => 'Seeder generated inventory transaction',

                'actual_quantity' => $actualQty,

                'expected_quantity' => $expectedQty,

                'variance' => $actualQty - $expectedQty,

                'user_id' => rand(1, 10),

                'tenant_id' => 1,

                'before_qty' => $beforeQty,

                'after_qty' => $afterQty,
            ];
        }

        DB::table('inventory_transaction')->insert($transactions);
    }
}
