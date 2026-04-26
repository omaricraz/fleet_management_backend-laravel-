<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'item' => fake()->words(2, true),
            'type' => 'unit',
            'price' => fake()->randomFloat(2, 1, 50),
            'unit_volume' => 0,
            'unit_weight' => 0,
        ];
    }
}
