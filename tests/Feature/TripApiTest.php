<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use App\Models\Zone;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenantWithFleet(): array
    {
        $tenant = Tenant::factory()->create();
        $zone = Zone::query()->create([
            'tenant_id' => $tenant->id,
            'city' => 'Test City',
            'number_of_stores' => 0,
        ]);
        $carA = Car::factory()->create(['tenant_id' => $tenant->id]);
        $carB = Car::factory()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        $driverUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'driver',
            'name' => 'Scoped Trip Driver',
        ]);
        $otherDriverUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'driver',
            'name' => 'Other Trip Driver',
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

        return compact(
            'tenant',
            'zone',
            'carA',
            'carB',
            'admin',
            'driverUser',
            'otherDriverUser',
            'driver',
            'otherDriver'
        );
    }

    public function test_cannot_create_second_active_trip_for_same_driver(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $r1 = $this->postJson('/api/v1/trips', [
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'zone_id' => $s['zone']->id,
        ]);
        $r1->assertStatus(201);

        $r2 = $this->postJson('/api/v1/trips', [
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carB']->id,
            'zone_id' => $s['zone']->id,
        ]);
        $r2->assertStatus(422);
        $this->assertStringContainsString('active trip', strtolower($r2->json('message')));
    }

    public function test_cannot_create_second_active_trip_for_same_car(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $this->postJson('/api/v1/trips', [
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
        ])->assertStatus(201);

        $r2 = $this->postJson('/api/v1/trips', [
            'driver_id' => $s['otherDriver']->id,
            'car_id' => $s['carA']->id,
        ]);
        $r2->assertStatus(422);
        $this->assertStringContainsString('car already has an active trip', strtolower($r2->json('message')));
    }

    public function test_cannot_start_trip_twice(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $trip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'zone_id' => $s['zone']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        $this->postJson("/api/v1/trips/{$trip->id}/start")->assertOk();
        $this->postJson("/api/v1/trips/{$trip->id}/start")->assertStatus(422);
    }

    public function test_cannot_depart_before_start(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $trip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'zone_id' => $s['zone']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        $this->postJson("/api/v1/trips/{$trip->id}/depart")->assertStatus(422);
    }

    public function test_cannot_end_from_invalid_state(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $trip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'zone_id' => $s['zone']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        $this->postJson("/api/v1/trips/{$trip->id}/end")->assertStatus(422);
    }

    public function test_lifecycle_creates_trip_events(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $trip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'zone_id' => $s['zone']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        $this->postJson("/api/v1/trips/{$trip->id}/start")->assertOk();
        $this->postJson("/api/v1/trips/{$trip->id}/depart")->assertOk();
        $this->postJson("/api/v1/trips/{$trip->id}/end")->assertOk();

        $types = TripEvent::query()->where('trip_id', $trip->id)->orderBy('id')->pluck('event_type')->all();
        $this->assertSame(
            [TripService::EVENT_START_TRIP, TripService::EVENT_DEPARTURE, TripService::EVENT_END_TRIP],
            $types
        );
    }

    public function test_trip_list_supports_status_filter(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        $res = $this->getJson('/api/v1/trips?status='.TripService::STATUS_READY);
        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertGreaterThanOrEqual(1, count($res->json('data')));
    }

    public function test_trip_detail_includes_timeline(): void
    {
        $s = $this->seedTenantWithFleet();
        Sanctum::actingAs($s['admin']);

        $trip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        $this->postJson("/api/v1/trips/{$trip->id}/start")->assertOk();

        $res = $this->getJson("/api/v1/trips/{$trip->id}");
        $res->assertOk();
        $timeline = $res->json('data.timeline');
        $this->assertIsArray($timeline);
        $this->assertNotEmpty($timeline);
        $this->assertSame(TripService::EVENT_START_TRIP, $timeline[0]['event_type']);
    }

    public function test_driver_cannot_access_another_drivers_trip(): void
    {
        $s = $this->seedTenantWithFleet();
        $trip = Trip::query()->create([
            'tenant_id' => $s['tenant']->id,
            'driver_id' => $s['driver']->id,
            'car_id' => $s['carA']->id,
            'status' => TripService::STATUS_READY,
        ]);

        Sanctum::actingAs($s['otherDriverUser']);
        $this->getJson("/api/v1/trips/{$trip->id}")->assertStatus(403);
    }
}
