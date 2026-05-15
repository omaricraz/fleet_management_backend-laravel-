<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    protected $model = Car::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'model' => $this->faker->word(),
            'plate_number' => strtoupper($this->faker->bothify('??-#####')),
            'overall_volume_capacity' => 0,
            'overall_weight_capacity' => 0,
            'fuel_efficiency' => 0,
            'color' => null,
        ];
    }
}
