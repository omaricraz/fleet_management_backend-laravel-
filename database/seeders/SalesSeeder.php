<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $sales = [];

        for ($i = 1; $i <= 300; $i++) {

            $quantity = rand(1, 50);

            $pricePerUnit = rand(5, 200);

            $totalPrice = $quantity * $pricePerUnit;

            $sales[] = [

                'quantity' => $quantity,

                'total_price' => number_format($totalPrice, 4, '.', ''),

                'trip_id' => rand(101, 200),

                // mediumblob can be null in seeder
                'sale_invoice_image' => null,

                'driver_id' => rand(124, 160),

                'customer_id' => rand(401, 500),

                'product_id' => rand(1, 10),

                'tenant_id' => 1,

                'created_at' => now()->subDays(rand(0, 30)),

                'updated_at' => now(),

                'deleted_at' => null,
            ];
        }

        DB::table('sales')->insert($sales);
    }
}
