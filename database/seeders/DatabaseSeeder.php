<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(FleetFoundationSeeder::class);
        $this->call(TenantSeeder::class,);
        $this->call(ZoneSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(CarSeeder::class);
        $this->call(DriverSeeder::class);
        $this->call(TripSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(InventorySeeder::class);
        $this->call(SalesSeeder::class);
        $this->call(InventoryTransactionSeeder::class);
    }
}
