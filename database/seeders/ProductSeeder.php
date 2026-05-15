<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [];

        $items = [
            ['item' => 'Coca Cola', 'type' => 'Drink'],
            ['item' => 'Pepsi', 'type' => 'Drink'],
            ['item' => 'Camel Milk', 'type' => 'Dairy'],
            ['item' => 'Rice', 'type' => 'Food'],
            ['item' => 'Sugar', 'type' => 'Food'],
            ['item' => 'Cooking Oil', 'type' => 'Food'],
            ['item' => 'Water Bottle', 'type' => 'Drink'],
            ['item' => 'Dates', 'type' => 'Snack'],
            ['item' => 'Biscuits', 'type' => 'Snack'],
            ['item' => 'Spaghetti', 'type' => 'Food'],
        ];

        foreach ($items as $product) {

            $products[] = [
                'item' => $product['item'],

                'type' => $product['type'],

                'price' => rand(1, 100),

                'unit_volume' => rand(1, 20),

                'unit_weight' => rand(1, 50),

                'tenant_id' => 1,

                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }

        DB::table('products')->insert($products);
    }
}
