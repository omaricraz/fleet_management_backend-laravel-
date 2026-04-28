<?php

namespace Tests\Feature;

use App\Exceptions\CarCapacityExceededException;
use App\Exceptions\InsufficientInventoryException;
use App\Models\Car;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\InventoryMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService;
    }

    public function test_opening_creates_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '12.5', $userId);

        $row = Inventory::query()
            ->where('tenant_id', $tenant->id)
            ->where('car_id', $car->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('12.500000', InventoryMath::normalize((string) $row->quantity));

        $this->assertSame(1, InventoryTransaction::query()->count());
        $this->assertSame(InventoryService::TYPE_ADJUSTMENT, InventoryTransaction::query()->first()->type);
    }

    public function test_load_increases_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '10', $userId);

        $this->service->applyLoad($tenant->id, $car->id, $product->id, '3', $userId);

        $this->assertSame('13.000000', $this->service->getCurrentQuantity($tenant->id, $car->id, $product->id));

        $load = InventoryTransaction::query()->where('type', InventoryService::TYPE_LOAD)->first();
        $this->assertNotNull($load);
        $this->assertSame('3.000000', InventoryMath::normalize((string) $load->quantity));
    }

    public function test_sale_decreases_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '10', $userId);

        $this->service->applySale($tenant->id, $car->id, $product->id, '4', $userId);

        $this->assertSame('6.000000', $this->service->getCurrentQuantity($tenant->id, $car->id, $product->id));
    }

    public function test_sale_below_zero_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '2', $userId);

        $this->expectException(InsufficientInventoryException::class);
        $this->service->applySale($tenant->id, $car->id, $product->id, '5', $userId);
    }

    public function test_closing_calculates_variance_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '100', $userId);

        $rows = $this->service->applyClosingCount($tenant->id, $car->id, [
            ['product_id' => $product->id, 'actual_quantity' => '97.25'],
        ], $userId);

        $this->assertCount(1, $rows);
        $this->assertSame('-2.750000', $rows[0]['variance']);
        $this->assertSame('97.250000', $this->service->getCurrentQuantity($tenant->id, $car->id, $product->id));

        $tx = InventoryTransaction::query()->orderByDesc('id')->first();
        $this->assertSame(InventoryService::TYPE_CLOSING_COUNT, $tx->type);
        $this->assertSame('-2.750000', InventoryMath::normalize((string) $tx->quantity));
        $this->assertSame($userId, (int) $tx->user_id);
    }

    public function test_adjustment_set_works(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '20', $userId);

        $this->service->applyAdjustment($tenant->id, $car->id, $product->id, 'set', '100', $userId);

        $this->assertSame('100.000000', $this->service->getCurrentQuantity($tenant->id, $car->id, $product->id));
    }

    public function test_return_requires_notes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->applyReturn($tenant->id, $car->id, $product->id, '1', $userId, null);
    }

    public function test_load_exceeds_volume_capacity_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create([
            'tenant_id' => $tenant->id,
            'overall_volume_capacity' => 100,
            'overall_weight_capacity' => 1_000_000,
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'unit_volume' => 10,
            'unit_weight' => 0,
        ]);

        $this->expectException(CarCapacityExceededException::class);
        $this->service->applyLoad($tenant->id, $car->id, $product->id, '11', $userId);
    }

    public function test_second_load_fails_when_cumulative_volume_exceeds_capacity(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create([
            'tenant_id' => $tenant->id,
            'overall_volume_capacity' => 100,
            'overall_weight_capacity' => 1_000_000,
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'unit_volume' => 10,
            'unit_weight' => 0,
        ]);

        $this->service->applyLoad($tenant->id, $car->id, $product->id, '5', $userId);

        $this->expectException(CarCapacityExceededException::class);
        $this->service->applyLoad($tenant->id, $car->id, $product->id, '6', $userId);
    }

    public function test_load_succeeds_when_capacity_above_projected_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $userId = (int) $user->id;
        $car = Car::factory()->create([
            'tenant_id' => $tenant->id,
            'overall_volume_capacity' => 1_000_000,
            'overall_weight_capacity' => 1_000_000,
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'unit_volume' => 500,
            'unit_weight' => 100,
        ]);

        $this->service->applyLoad($tenant->id, $car->id, $product->id, '50', $userId);

        $this->assertSame('50.000000', $this->service->getCurrentQuantity($tenant->id, $car->id, $product->id));
    }
}
