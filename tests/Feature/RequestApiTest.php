<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\FleetRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function seedTenantWithDrivers(): array
    {
        $tenant = Tenant::factory()->create();
        $zone = Zone::query()->create([
            'tenant_id' => $tenant->id,
            'city' => 'Test City',
            'number_of_stores' => 0,
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        $manager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'manager',
        ]);
        $driverUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'driver',
            'name' => 'Request Driver A',
        ]);
        $otherDriverUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'driver',
            'name' => 'Request Driver B',
        ]);
        $driverA = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $driverUser->name,
            'phone' => '+1000000001',
            'zone_id' => $zone->id,
        ]);
        $driverB = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $otherDriverUser->name,
            'phone' => '+1000000002',
            'zone_id' => $zone->id,
        ]);

        return compact(
            'tenant',
            'zone',
            'admin',
            'manager',
            'driverUser',
            'otherDriverUser',
            'driverA',
            'driverB',
        );
    }

    public function test_driver_can_create_fuel_request(): void
    {
        $s = $this->seedTenantWithDrivers();
        Sanctum::actingAs($s['driverUser']);

        $res = $this->postJson('/api/v1/requests', [
            'type' => 'fuel',
            'fuel_requested' => 50,
            'litre_cost' => 1.5,
            'notes' => 'Need fuel before trip',
        ]);

        $res->assertCreated();
        $res->assertJsonPath('success', true);
        $this->assertDatabaseHas('requests', [
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
        ]);
    }

    public function test_driver_can_create_maintenance_request(): void
    {
        $s = $this->seedTenantWithDrivers();
        Sanctum::actingAs($s['driverUser']);

        $res = $this->postJson('/api/v1/requests', [
            'type' => 'maintenance',
            'maintenance_requested' => 'Brake pads replacement',
            'notes' => 'Brakes worn out',
        ]);

        $res->assertCreated();
        $res->assertJsonPath('data.type', 'maintenance');
        $res->assertJsonPath('data.maintenance_requested', 'Brake pads replacement');
    }

    public function test_admin_can_view_all_requests(): void
    {
        $s = $this->seedTenantWithDrivers();
        FleetRequest::query()->create([
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
            'fuel_requested' => 10,
            'litre_cost' => 2,
        ]);
        FleetRequest::query()->create([
            'driver_id' => $s['driverB']->id,
            'user_id' => $s['otherDriverUser']->id,
            'type' => 'maintenance',
            'status' => RequestService::STATUS_PENDING,
            'maintenance_requested' => 'Oil change',
        ]);

        Sanctum::actingAs($s['admin']);
        $res = $this->getJson('/api/v1/requests');
        $res->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_manager_can_list_and_approve(): void
    {
        $s = $this->seedTenantWithDrivers();
        $fr = FleetRequest::query()->create([
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
            'fuel_requested' => 10,
            'litre_cost' => 2,
        ]);

        Sanctum::actingAs($s['manager']);
        $this->getJson('/api/v1/requests')->assertOk();
        $this->postJson("/api/v1/requests/{$fr->id}/approve")->assertOk();
        $this->assertSame(RequestService::STATUS_APPROVED, $fr->fresh()->status);
    }

    public function test_driver_only_sees_own_requests_on_my(): void
    {
        $s = $this->seedTenantWithDrivers();
        FleetRequest::query()->create([
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'inventory',
            'status' => RequestService::STATUS_PENDING,
            'notes' => 'Stock',
        ]);
        FleetRequest::query()->create([
            'driver_id' => $s['driverB']->id,
            'user_id' => $s['otherDriverUser']->id,
            'type' => 'inventory',
            'status' => RequestService::STATUS_PENDING,
            'notes' => 'Other',
        ]);

        Sanctum::actingAs($s['driverUser']);
        $res = $this->getJson('/api/v1/requests/my');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Stock', $res->json('data.0.notes'));
    }

    public function test_cannot_approve_twice(): void
    {
        $s = $this->seedTenantWithDrivers();
        $fr = FleetRequest::query()->create([
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
            'fuel_requested' => 10,
            'litre_cost' => 2,
        ]);

        Sanctum::actingAs($s['admin']);
        $this->postJson("/api/v1/requests/{$fr->id}/approve")->assertOk();
        $this->postJson("/api/v1/requests/{$fr->id}/approve")->assertStatus(422);
    }

    public function test_cannot_reject_twice(): void
    {
        $s = $this->seedTenantWithDrivers();
        $fr = FleetRequest::query()->create([
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
            'fuel_requested' => 10,
            'litre_cost' => 2,
        ]);

        Sanctum::actingAs($s['admin']);
        $this->postJson("/api/v1/requests/{$fr->id}/reject", [
            'notes' => 'No budget',
        ])->assertOk();
        $this->postJson("/api/v1/requests/{$fr->id}/reject", [
            'notes' => 'Again',
        ])->assertStatus(422);
    }

    public function test_validation_requires_fuel_fields_for_fuel_type(): void
    {
        $s = $this->seedTenantWithDrivers();
        Sanctum::actingAs($s['driverUser']);

        $this->postJson('/api/v1/requests', [
            'type' => 'fuel',
            'notes' => 'x',
        ])->assertStatus(422);
    }

    public function test_validation_requires_maintenance_text_for_maintenance_type(): void
    {
        $s = $this->seedTenantWithDrivers();
        Sanctum::actingAs($s['driverUser']);

        $this->postJson('/api/v1/requests', [
            'type' => 'maintenance',
            'notes' => 'x',
        ])->assertStatus(422);
    }

    public function test_driver_cannot_view_other_driver_request(): void
    {
        $s = $this->seedTenantWithDrivers();
        $fr = FleetRequest::query()->create([
            'driver_id' => $s['driverA']->id,
            'user_id' => $s['driverUser']->id,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
            'fuel_requested' => 10,
            'litre_cost' => 2,
        ]);

        Sanctum::actingAs($s['otherDriverUser']);
        $this->getJson("/api/v1/requests/{$fr->id}")->assertStatus(403);
    }

    public function test_cross_tenant_request_not_accessible(): void
    {
        $s = $this->seedTenantWithDrivers();
        $otherTenant = Tenant::factory()->create();
        $zoneOther = Zone::query()->create([
            'tenant_id' => $otherTenant->id,
            'city' => 'Other',
            'number_of_stores' => 0,
        ]);
        $driverOther = Driver::query()->create([
            'tenant_id' => $otherTenant->id,
            'full_name' => 'Remote',
            'phone' => '+1999',
            'zone_id' => $zoneOther->id,
        ]);
        $fr = FleetRequest::query()->create([
            'driver_id' => $driverOther->id,
            'user_id' => null,
            'type' => 'fuel',
            'status' => RequestService::STATUS_PENDING,
            'fuel_requested' => 5,
            'litre_cost' => 1,
        ]);

        Sanctum::actingAs($s['admin']);
        $this->getJson("/api/v1/requests/{$fr->id}")->assertStatus(404);
    }
}
