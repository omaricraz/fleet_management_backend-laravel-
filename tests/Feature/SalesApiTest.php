<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use App\Models\Zone;
use App\Services\InventoryService;
use App\Services\SalesService;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function seedSellingTripWithInventory(float $startingQty = 100.0): array
    {
        $tenant = Tenant::factory()->create();
        $zone = Zone::query()->create([
            'tenant_id' => $tenant->id,
            'city' => 'Sales City',
            'number_of_stores' => 0,
        ]);
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        $driverUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'driver',
            'name' => 'Sales Trip Driver',
        ]);
        $otherDriverUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'driver',
            'name' => 'Other Sales Driver',
        ]);
        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $driverUser->name,
            'phone' => '+1000000001',
            'zone_id' => $zone->id,
        ]);
        $otherDriver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $otherDriverUser->name,
            'phone' => '+1000000002',
            'zone_id' => $zone->id,
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'zone_id' => $zone->id,
            'full_name' => 'Retail Customer',
            'phone' => '+19990000001',
            'trip_id' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
        $trip = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'zone_id' => $zone->id,
            'driver_id' => $driver->id,
            'car_id' => $car->id,
            'status' => TripService::STATUS_SELLING,
        ]);

        $inventory = app(InventoryService::class);
        $inventory->applyAdjustment(
            $tenant->id,
            $car->id,
            $product->id,
            'set',
            (string) $startingQty,
            (int) $admin->id,
        );

        return compact(
            'tenant',
            'zone',
            'car',
            'product',
            'admin',
            'driverUser',
            'otherDriverUser',
            'driver',
            'otherDriver',
            'customer',
            'trip',
            'inventory',
        );
    }

    public function test_cannot_create_sale_without_trip_in_body(): void
    {
        $s = $this->seedSellingTripWithInventory();
        Sanctum::actingAs($s['admin']);

        $res = $this->postJson('/api/v1/sales', [
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 2,
            'total_price' => 10,
        ]);

        $res->assertStatus(422);
        $this->assertArrayHasKey('errors', $res->json());
    }

    public function test_cannot_create_sale_when_trip_not_selling(): void
    {
        $s = $this->seedSellingTripWithInventory();
        $s['trip']->forceFill(['status' => TripService::STATUS_IN_TRANSIT])->save();

        Sanctum::actingAs($s['admin']);

        $res = $this->postJson('/api/v1/sales', [
            'trip_id' => $s['trip']->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 2,
            'total_price' => 10,
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('selling', strtolower($res->json('message')));
    }

    public function test_cannot_oversell_inventory(): void
    {
        $s = $this->seedSellingTripWithInventory(5);
        Sanctum::actingAs($s['admin']);

        $res = $this->postJson('/api/v1/sales', [
            'trip_id' => $s['trip']->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 20,
            'total_price' => 99,
        ]);

        $res->assertStatus(422);
        $this->assertFalse($res->json('success'));
    }

    public function test_sale_decreases_inventory_and_writes_trip_event(): void
    {
        $s = $this->seedSellingTripWithInventory(100);
        Sanctum::actingAs($s['admin']);

        $res = $this->postJson('/api/v1/sales', [
            'trip_id' => $s['trip']->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 12,
            'total_price' => 120.5,
        ]);

        $res->assertCreated();
        $res->assertJsonPath('success', true);
        $saleId = (int) $res->json('data.id');

        $afterQty = app(InventoryService::class)->getCurrentQuantity($s['tenant']->id, $s['car']->id, $s['product']->id);
        $this->assertSame('88.000000', $afterQty);

        $evt = TripEvent::query()->where('trip_id', $s['trip']->id)->where('event_type', SalesService::EVENT_SALE)->first();
        $this->assertNotNull($evt);
        $this->assertSame((int) $s['admin']->id, (int) $evt->user_id);
        $this->assertSame('12.00', (string) $evt->quantity);
        $this->assertSame((int) $s['product']->id, (int) $evt->product_id);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'trip_id' => $s['trip']->id,
        ]);
    }

    public function test_driver_only_sees_own_sales_on_my_endpoint(): void
    {
        $s = $this->seedSellingTripWithInventory();

        $otherTrip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'zone_id' => $s['zone']->id,
            'driver_id' => $s['otherDriver']->id,
            'car_id' => $s['car']->id,
            'status' => TripService::STATUS_SELLING,
        ]);

        Sale::query()->create([
            'tenant_id' => $s['tenant']->id,
            'trip_id' => $s['trip']->id,
            'driver_id' => $s['driver']->id,
            'customer_id' => $s['customer']->id,
            'product_id' => $s['product']->id,
            'quantity' => 1,
            'total_price' => '5.0000',
        ]);

        Sale::query()->create([
            'tenant_id' => $s['tenant']->id,
            'trip_id' => $otherTrip->id,
            'driver_id' => $s['otherDriver']->id,
            'customer_id' => $s['customer']->id,
            'product_id' => $s['product']->id,
            'quantity' => 1,
            'total_price' => '7.0000',
        ]);

        Sanctum::actingAs($s['driverUser']);
        $res = $this->getJson('/api/v1/sales/my');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame((int) $s['driver']->id, (int) $res->json('data.0.driver_id'));
    }

    public function test_delete_sale_restores_inventory(): void
    {
        $s = $this->seedSellingTripWithInventory(40);
        Sanctum::actingAs($s['admin']);

        $create = $this->postJson('/api/v1/sales', [
            'trip_id' => $s['trip']->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 10,
            'total_price' => 50,
        ]);
        $saleId = (int) $create->json('data.id');

        $qtyAfterSale = app(InventoryService::class)->getCurrentQuantity($s['tenant']->id, $s['car']->id, $s['product']->id);
        $this->assertSame('30.000000', $qtyAfterSale);

        $del = $this->deleteJson("/api/v1/sales/{$saleId}");
        $del->assertOk();

        $qtyRestored = app(InventoryService::class)->getCurrentQuantity($s['tenant']->id, $s['car']->id, $s['product']->id);
        $this->assertSame('40.000000', $qtyRestored);

        $this->assertNotNull(Sale::withTrashed()->find($saleId)?->deleted_at);
    }

    public function test_filters_limit_index_results(): void
    {
        $s = $this->seedSellingTripWithInventory(200);

        $tripB = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'zone_id' => $s['zone']->id,
            'driver_id' => $s['otherDriver']->id,
            'car_id' => $s['car']->id,
            'status' => TripService::STATUS_SELLING,
        ]);

        Sanctum::actingAs($s['admin']);

        $this->postJson('/api/v1/sales', [
            'trip_id' => $s['trip']->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 2,
            'total_price' => 10,
        ])->assertCreated();

        $this->postJson('/api/v1/sales', [
            'trip_id' => $tripB->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 2,
            'total_price' => 10,
        ])->assertCreated();

        $byTrip = $this->getJson('/api/v1/sales?trip_id='.$s['trip']->id);
        $byTrip->assertOk();
        $this->assertCount(1, $byTrip->json('data'));
        $this->assertSame((int) $s['trip']->id, (int) $byTrip->json('data.0.trip_id'));
    }

    public function test_driver_cannot_create_sale_on_another_drivers_trip(): void
    {
        $s = $this->seedSellingTripWithInventory();

        Sanctum::actingAs($s['otherDriverUser']);

        $res = $this->postJson('/api/v1/sales', [
            'trip_id' => $s['trip']->id,
            'product_id' => $s['product']->id,
            'customer_id' => $s['customer']->id,
            'quantity' => 1,
            'total_price' => 10,
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('own', strtolower($res->json('message')));
    }

    public function test_driver_cannot_view_other_drivers_sale(): void
    {
        $s = $this->seedSellingTripWithInventory();

        $sale = Sale::query()->create([
            'tenant_id' => $s['tenant']->id,
            'trip_id' => $s['trip']->id,
            'driver_id' => $s['driver']->id,
            'customer_id' => $s['customer']->id,
            'product_id' => $s['product']->id,
            'quantity' => 1,
            'total_price' => '5.0000',
        ]);

        Sanctum::actingAs($s['otherDriverUser']);
        $this->getJson("/api/v1/sales/{$sale->id}")->assertStatus(403);
    }
}
