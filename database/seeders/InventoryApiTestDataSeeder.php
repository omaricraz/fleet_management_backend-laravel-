<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use App\Services\TripService;
use Illuminate\Database\Seeder;

/**
 * Test data for inventory API endpoints. Intended to run after FleetFoundationSeeder
 * (same tenant, same login users: admin/manager/driver @test.com, password: password).
 *
 * Creates: zone, 2 cars, 3 products, 1 driver linked to driver user (by full_name match),
 * an open trip (driver → car 1) for GET /api/v1/driver/inventory, and a small on-hand
 * line on car 1 for GET /api/v1/inventory snapshot smoke tests.
 */
class InventoryApiTestDataSeeder extends Seeder
{
    public const CAR_PLATE_A = 'INV-SEED-A';

    public const CAR_PLATE_B = 'INV-SEED-B';

    public const PRODUCT_A = 'Seed Product A';

    public const PRODUCT_B = 'Seed Product B';

    public const PRODUCT_C = 'Seed Product C';

    public function run(): void
    {
        $tenant = Tenant::query()->where('name', 'Zamzam Group')->first()
            ?? Tenant::query()->first();

        if ($tenant === null) {
            $this->command?->warn('No tenant found. Run FleetFoundationSeeder (or create a tenant) first.');

            return;
        }

        $driverUser = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'driver@test.com')
            ->where('is_platform_admin', false)
            ->first();

        $zone = Zone::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'city' => 'Seed City'],
            ['number_of_stores' => 0]
        );

        $carA = Car::query()->updateOrCreate(
            ['plate_number' => self::CAR_PLATE_A],
            [
                'tenant_id' => $tenant->id,
                'model' => 'Seed Van A',
                'overall_volume_capacity' => 100,
                'overall_weight_capacity' => 200,
                'fuel_efficiency' => 10,
                'color' => 'white',
            ]
        );

        $carB = Car::query()->updateOrCreate(
            ['plate_number' => self::CAR_PLATE_B],
            [
                'tenant_id' => $tenant->id,
                'model' => 'Seed Van B',
                'overall_volume_capacity' => 80,
                'overall_weight_capacity' => 150,
                'fuel_efficiency' => 9,
                'color' => 'blue',
            ]
        );

        $productA = Product::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'item' => self::PRODUCT_A],
            [
                'type' => 'unit',
                'price' => 5.5,
                'unit_volume' => 1,
                'unit_weight' => 0.5,
            ]
        );

        $productB = Product::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'item' => self::PRODUCT_B],
            [
                'type' => 'unit',
                'price' => 3.0,
                'unit_volume' => 1,
                'unit_weight' => 0.3,
            ]
        );

        $productC = Product::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'item' => self::PRODUCT_C],
            [
                'type' => 'unit',
                'price' => 2.0,
                'unit_volume' => 1,
                'unit_weight' => 0.2,
            ]
        );

        if ($driverUser !== null) {
            $driver = Driver::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'phone' => '+1000000SEED1',
                ],
                [
                    'full_name' => $driverUser->name,
                    'zone_id' => $zone->id,
                ]
            );

            if ($driver->full_name !== $driverUser->name) {
                $driver->fill(['full_name' => $driverUser->name])->save();
            }

            Trip::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'driver_id' => $driver->id,
                    'car_id' => $carA->id,
                ],
                [
                    'zone_id' => $zone->id,
                    'start_date' => now(),
                    'end_date' => null,
                    'status' => TripService::STATUS_LOADING,
                ]
            );
        } else {
            $this->command?->warn('No driver user (driver@test.com) found; skipped driver + trip. Driver inventory API will be empty until you add one.');
        }

        // Light on-hand row on car A for product A (so GET /api/v1/inventory is non-empty)
        $existing = Inventory::query()
            ->where('tenant_id', $tenant->id)
            ->where('car_id', $carA->id)
            ->where('product_id', $productA->id)
            ->first();

        if ($existing === null) {
            Inventory::query()->create([
                'tenant_id' => $tenant->id,
                'car_id' => $carA->id,
                'product_id' => $productA->id,
                'quantity' => 25.0,
            ]);
        }

        if ($this->command !== null) {
            $this->command->newLine();
            $this->command->info('Inventory API test data ready.');
            $this->command->line('  Tenant ID: '.$tenant->id);
            $this->command->line('  Car A: '.$carA->id.' (plate '.self::CAR_PLATE_A.')');
            $this->command->line('  Car B: '.$carB->id.' (plate '.self::CAR_PLATE_B.')');
            $this->command->line('  Products: '.$productA->id.' / '.$productB->id.' / '.$productC->id);
            if ($driverUser !== null) {
                $this->command->line('  Driver user matches Driver row: '.$driverUser->name.' (GET /api/v1/driver/inventory → car A)');
            }
        }
    }
}
