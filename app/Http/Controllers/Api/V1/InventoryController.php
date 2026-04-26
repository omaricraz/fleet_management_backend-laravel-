<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CarInventoryShowRequest;
use App\Http\Requests\Inventory\InventoryAdjustmentRequest;
use App\Http\Requests\Inventory\InventoryCarBatchRequest;
use App\Http\Requests\Inventory\InventoryClosingCountRequest;
use App\Http\Requests\Inventory\InventoryReturnRequest;
use App\Http\Requests\Inventory\ListFleetInventoryRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class InventoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function index(ListFleetInventoryRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $v = $request->validated();
        $lowStockOnly = isset($v['low_stock']) && (int) $v['low_stock'] === 1;

        $data = $this->inventory->getFleetSnapshot(
            $tenantId,
            $v['car_id'] ?? null,
            $v['product_id'] ?? null,
            $lowStockOnly,
            $v['search'] ?? null
        );

        return $this->successResponse('Success', $data);
    }

    public function showForCar(
        CarInventoryShowRequest $request,
        Car $car
    ): JsonResponse {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $limit = $request->validated()['limit'] ?? null;

        $data = $this->inventory->getCarInventoryWithHistory($tenantId, (int) $car->id, $limit);

        return $this->successResponse('Success', [
            'car' => new CarResource($car),
            'snapshot' => $data['snapshot'],
            'transactions' => $data['transactions'],
        ]);
    }

    public function alerts(): JsonResponse
    {
        $tenantId = (int) request()->attributes->get('tenant_id');
        $data = $this->inventory->getAlerts($tenantId);

        return $this->successResponse('Success', $data);
    }

    public function openingBalance(InventoryCarBatchRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $userId = (int) $request->user()->id;
        $results = [];

        try {
            foreach ($request->input('cars', []) as $row) {
                $carId = (int) $row['car_id'];
                $tripId = $row['trip_id'] ?? null;
                if ($tripId !== null) {
                    $tripId = (int) $tripId;
                }
                $lines = $this->inventory->applyOpeningBalance(
                    $tenantId,
                    $userId,
                    $carId,
                    $tripId,
                    $row['items']
                );
                $results[] = [
                    'car_id' => $carId,
                    'lines' => $lines,
                ];
            }
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Opening balance applied successfully', $results);
    }

    public function load(InventoryCarBatchRequest $request): JsonResponse
    {
        return $this->processItemBatch(
            $request,
            'Inventory loaded successfully',
            fn (int $tenantId, int $carId, int $productId, string|int|float $quantity, ?int $tripId): array => $this->inventory->applyLoad(
                $tenantId,
                $carId,
                $productId,
                $quantity,
                $tripId
            )
        );
    }

    public function manualSale(InventoryCarBatchRequest $request): JsonResponse
    {
        return $this->processItemBatch(
            $request,
            'Sale applied successfully',
            fn (int $tenantId, int $carId, int $productId, string|int|float $quantity, ?int $tripId): array => $this->inventory->applySale(
                $tenantId,
                $carId,
                $productId,
                $quantity,
                $tripId,
                null
            )
        );
    }

    public function returnInventory(InventoryReturnRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $notes = (string) $request->input('notes');
        $results = [];

        try {
            foreach ($request->input('cars', []) as $row) {
                $carId = (int) $row['car_id'];
                $tripId = $row['trip_id'] ?? null;
                if ($tripId !== null) {
                    $tripId = (int) $tripId;
                }
                foreach ($row['items'] as $item) {
                    $results[] = $this->inventory->applyReturn(
                        $tenantId,
                        $carId,
                        (int) $item['product_id'],
                        $item['quantity'],
                        $notes,
                        $tripId
                    );
                }
            }
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Return applied successfully', $results);
    }

    public function adjustment(InventoryAdjustmentRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $v = $request->validated();
        $carId = (int) $v['car_id'];
        $tripId = isset($v['trip_id']) ? (int) $v['trip_id'] : null;
        $results = [];

        try {
            foreach ($v['items'] as $row) {
                $results[] = $this->inventory->applyAdjustment(
                    $tenantId,
                    $carId,
                    (int) $row['product_id'],
                    (string) $row['mode'],
                    $row['quantity'],
                    $tripId
                );
            }
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Adjustment applied successfully', $results);
    }

    public function closeCount(InventoryClosingCountRequest $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $v = $request->validated();
        $carId = (int) $v['car_id'];
        $tripId = $v['trip_id'] ?? null;
        if ($tripId !== null) {
            $tripId = (int) $tripId;
        }
        $counts = [];

        foreach ($v['items'] as $row) {
            $counts[] = [
                'product_id' => (int) $row['product_id'],
                'actual_quantity' => $row['actual_quantity'],
            ];
        }

        try {
            $rows = $this->inventory->applyClosingCount($tenantId, $carId, $counts, $tripId);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse('Closing count applied successfully', $rows);
    }

    /**
     * @param  callable(int, int, int, string|int|float, ?int): array  $op
     */
    private function processItemBatch(
        InventoryCarBatchRequest $request,
        string $message,
        callable $op
    ): JsonResponse {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $results = [];

        try {
            foreach ($request->input('cars', []) as $row) {
                $carId = (int) $row['car_id'];
                $tripId = $row['trip_id'] ?? null;
                if ($tripId !== null) {
                    $tripId = (int) $tripId;
                }
                foreach ($row['items'] as $item) {
                    $r = $op(
                        $tenantId,
                        $carId,
                        (int) $item['product_id'],
                        $item['quantity'],
                        $tripId
                    );
                    $results[] = array_merge(
                        [
                            'car_id' => $carId,
                            'product_id' => (int) $item['product_id'],
                        ],
                        is_array($r) ? $r : []
                    );
                }
            }
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), (object) [], 422);
        }

        return $this->successResponse($message, $results);
    }
}
