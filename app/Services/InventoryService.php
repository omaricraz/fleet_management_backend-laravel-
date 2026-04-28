<?php

namespace App\Services;

use App\DataTransferObjects\InventoryOperationData;
use App\Exceptions\CarCapacityExceededException;
use App\Exceptions\InsufficientInventoryException;
use App\Models\Car;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Trip;
use App\Support\InventoryMath;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fleet inventory snapshot + ledger operations (tenant scoped).
 *
 * Ledger persistence follows the locked schema: only types load, sale, adjustment.
 * Opening, return, closing, and non-load/sale adjustments are stored as adjustment
 * rows using signed quantity deltas where applicable.
 */
final class InventoryService
{
    public const TYPE_LOAD = 'load';

    public const TYPE_SALE = 'sale';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_RETURN = 'return';

    public const TYPE_CLOSING_COUNT = 'closing';

    public const TYPE_OPENING_BALANCE = 'opening';

    public function __construct(
        private readonly int $lowStockThreshold = 5,
        private readonly int $defaultHistoryLimit = 50,
        private readonly int $alertsLookbackDays = 14,
        private readonly int $repeatedShortageMinEvents = 2,
    ) {}

    public function getCurrentQuantity(int $tenantId, int $carId, int $productId): string
    {
        $row = $this->snapshotQuery($tenantId, $carId, $productId)->first();

        return $row ? InventoryMath::normalize((string) $row->quantity) : '0.000000';
    }

    /**
     * @param  list<array{product_id: int|string, quantity: string|int|float}>|list<InventoryOperationData>  $items
     * @return list<array{product_id: int, before_qty: string, after_qty: string, transaction_id: int}>
     */
    //need to justify it's usecase before letting exist again !!!!!
    
    // public function applyOpeningBalance(
    //     int $tenantId,
    //     ?int $userId,
    //     int $carId,
    //     ?int $tripId,
    //     array $items,
    // ): array {
    //     $this->assertTenantOwnsCar($tenantId, $carId);

    //     $normalized = $this->normalizeItemInputs($items);

    //     return DB::transaction(function () use ($tenantId, $carId, $tripId, $normalized): array {
    //         $results = [];

    //         foreach ($normalized as $item) {
    //             $before = $this->getCurrentQuantity($tenantId, $carId, $item->productId);
    //             $after = $item->quantity;
    //             $delta = InventoryMath::sub($after, $before);

    //             $this->persistSnapshotQty($tenantId, $carId, $item->productId, $after, $tripId);

    //             $tx = $this->createTransaction(
    //                 $tenantId,
    //                 $carId,
    //                 $item->productId,
    //                 self::TYPE_OPENING_BALANCE,
    //                 $delta,
    //                 $tripId,
    //                 null,
    //                 $before,
    //                 $after
    //             );

    //             $results[] = [
    //                 'product_id' => $item->productId,
    //                 'before_qty' => $before,
    //                 'after_qty' => $after,
    //                 'transaction_id' => $tx->id,
    //             ];
    //         }

    //         return $results;
    //     });
    // }

    /**
     * @return array{before_qty: string, after_qty: string, transaction_id: int}
     */
    public function applyLoad(
        int $tenantId,
        int $carId,
        int $productId,
        string|int|float $quantity,
        int $userId,
        ?int $tripId = null,
    ): array {
        $qty = $this->requireNonNegativeQuantity($quantity);
        $this->assertTenantOwnsCar($tenantId, $carId);
        $this->assertTenantOwnsProduct($tenantId, $productId);

        return DB::transaction(function () use ($tenantId, $carId, $productId, $qty, $tripId, $userId): array {
            $before = $this->getCurrentQuantity($tenantId, $carId, $productId);
            $after = InventoryMath::add($before, $qty);
            $this->assertCarCapacityAllowsLoad($tenantId, $carId, $productId, $after);
            $this->persistSnapshotQty($tenantId, $carId, $productId, $after, null);

            $tx = $this->createTransaction(
                $tenantId,
                $carId,
                $productId,
                self::TYPE_LOAD,
                $qty,
                $tripId,
                null,
                $before,
                $after,
                null,
                null,
                null,
                null,
                $userId,
            );

            $this->assertNonNegativeSnapshot($tenantId, $carId, $productId, $after);

            return [
                'before_qty' => $before,
                'after_qty' => $after,
                'transaction_id' => $tx->id,
            ];
        });
    }

    /**
     * @return array{before_qty: string, after_qty: string, transaction_id: int}
     */
    public function applySale(
        int $tenantId,
        int $carId,
        int $productId,
        string|int|float $quantity,
        int $userId,
        ?int $tripId = null,
        ?int $saleId = null,
    ): array {
        $qty = $this->requireNonNegativeQuantity($quantity);
        $this->assertTenantOwnsCar($tenantId, $carId);
        $this->assertTenantOwnsProduct($tenantId, $productId);

        return DB::transaction(function () use ($tenantId, $carId, $productId, $qty, $tripId, $saleId, $userId): array {
            $before = $this->getCurrentQuantity($tenantId, $carId, $productId);

            if (InventoryMath::compare($before, $qty) < 0) {
                throw new InsufficientInventoryException(
                    $tenantId,
                    $carId,
                    $productId,
                    $before,
                    $qty
                );
            }

            $after = InventoryMath::sub($before, $qty);
            $this->persistSnapshotQty($tenantId, $carId, $productId, $after, null);

            $tx = $this->createTransaction(
                $tenantId,
                $carId,
                $productId,
                self::TYPE_SALE,
                $qty,
                $tripId,
                $saleId,
                $before,
                $after,
                null,
                null,
                null,
                null,
                $userId,
            );

            $this->assertNonNegativeSnapshot($tenantId, $carId, $productId, $after);

            return [
                'before_qty' => $before,
                'after_qty' => $after,
                'transaction_id' => $tx->id,
            ];
        });
    }

    /**
     * Removes stock for damaged / lost / returned product (not a customer sale).
     *
     * @return array{before_qty: string, after_qty: string, transaction_id: int, variance: string}
     */
    public function applyReturn(
        int $tenantId,
        int $carId,
        int $productId,
        string|int|float $quantity,
        int $userId,
        ?string $notes = null,
        ?int $tripId = null,
    ): array {
        if ($notes === null || trim($notes) === '') {
            throw new InvalidArgumentException('Return operations should include notes describing the reason.');
        }

        $qty = $this->requireNonNegativeQuantity($quantity);
        $this->assertTenantOwnsCar($tenantId, $carId);
        $this->assertTenantOwnsProduct($tenantId, $productId);

        return DB::transaction(function () use ($tenantId, $carId, $productId, $qty, $tripId, $notes, $userId): array {
            $before = $this->getCurrentQuantity($tenantId, $carId, $productId);

            if (InventoryMath::compare($before, $qty) < 0) {
                throw new InsufficientInventoryException(
                    $tenantId,
                    $carId,
                    $productId,
                    $before,
                    $qty
                );
            }

            $after = InventoryMath::sub($before, $qty);
            $ledgerDelta = InventoryMath::sub($after, $before);

            $this->persistSnapshotQty($tenantId, $carId, $productId, $after, null);

            $tx = $this->createTransaction(
                $tenantId,
                $carId,
                $productId,
                self::TYPE_RETURN,
                $ledgerDelta,
                $tripId,
                null,
                $before,
                $after,
                $notes,
                null,
                null,
                null,
                $userId

            );

            $this->assertNonNegativeSnapshot($tenantId, $carId, $productId, $after);

            return [
                'before_qty' => $before,
                'after_qty' => $after,
                'transaction_id' => $tx->id,
                'variance' => $ledgerDelta,
            ];
        });
    }

    /**
     * @param  'increase'|'decrease'|'set'  $mode
     * @return array{before_qty: string, after_qty: string, transaction_id: int, ledger_quantity: string}
     */
    public function applyAdjustment(
        int $tenantId,
        int $carId,
        int $productId,
        string $mode,
        string|int|float $quantity,
        int $userId,
        ?int $tripId = null,
    ): array {
        if (! in_array($mode, ['increase', 'decrease', 'set'], true)) {
            throw new InvalidArgumentException('Adjustment mode must be increase, decrease, or set.');
        }

        $this->assertTenantOwnsCar($tenantId, $carId);
        $this->assertTenantOwnsProduct($tenantId, $productId);

        return DB::transaction(function () use ($tenantId, $carId, $productId, $mode, $quantity, $tripId, $userId): array {
            $before = $this->getCurrentQuantity($tenantId, $carId, $productId);

            $after = match ($mode) {
                'increase' => InventoryMath::add($before, $this->requireNonNegativeQuantity($quantity)),
                'decrease' => $this->subtractForDecrease(
                    $tenantId,
                    $carId,
                    $productId,
                    $before,
                    $this->requireNonNegativeQuantity($quantity)
                ),
                'set' => InventoryMath::normalize($quantity),
            };

            if (InventoryMath::isNegative($after)) {
                throw new InvalidArgumentException('Adjustment would result in negative on-hand quantity.');
            }

            $ledgerQty = InventoryMath::sub($after, $before);

            $this->persistSnapshotQty($tenantId, $carId, $productId, $after, null);

            $tx = $this->createTransaction(
                $tenantId,
                $carId,
                $productId,
                self::TYPE_ADJUSTMENT,
                $ledgerQty,
                $tripId,
                null,
                $before,
                $after,
                null,
                null,
                null,
                null,
                $userId
            );

            $this->assertNonNegativeSnapshot($tenantId, $carId, $productId, $after);

            return [
                'before_qty' => $before,
                'after_qty' => $after,
                'transaction_id' => $tx->id,
                'ledger_quantity' => $ledgerQty,
            ];
        });
    }

    /**
     * @param  list<array{product_id: int|string, actual_quantity: string|int|float}>  $counts
     * @return list<array{
     *     product_id: int,
     *     expected_quantity: string,
     *     actual_quantity: string,
     *     variance: string,
     *     before_qty: string,
     *     after_qty: string,
     *     transaction_id: int
     * }>
     */
    public function applyClosingCount(
        int $tenantId,
        int $carId,
        array $counts,
        int $userId,
        ?int $tripId = null,
    ): array {
        $this->assertTenantOwnsCar($tenantId, $carId);

        return DB::transaction(function () use ($tenantId, $carId, $counts, $tripId, $userId): array {
            $out = [];

            foreach ($counts as $row) {
                if (! isset($row['product_id'], $row['actual_quantity'])) {
                    throw new InvalidArgumentException('Each closing count row requires product_id and actual_quantity.');
                }

                $productId = (int) $row['product_id'];
                $this->assertTenantOwnsProduct($tenantId, $productId);

                $actual = $this->requireNonNegativeQuantity($row['actual_quantity']);
                $expected = $this->getCurrentQuantity($tenantId, $carId, $productId);
                $variance = InventoryMath::sub($actual, $expected);
                $this->persistSnapshotQty($tenantId, $carId, $productId, $actual, null);

                $tx = $this->createTransaction(
                    $tenantId,
                    $carId,
                    $productId,
                    self::TYPE_CLOSING_COUNT,
                    null,
                    $tripId,
                    null,
                    null,
                    null,
                    null,
                    $actual,
                    $expected,
                    $variance,
                    $userId,
                );

                $this->assertNonNegativeSnapshot($tenantId, $carId, $productId, $actual);

                $out[] = [
                    'product_id' => $productId,
                    'expected_quantity' => $expected,
                    'actual_quantity' => $actual,
                    'variance' => $variance,
                    'before_qty' => $expected,
                    'after_qty' => $actual,
                    'transaction_id' => $tx->id,
                ];
            }

            return $out;
        });
    }

    /**
     * @return list<array{car_id: int, car_name: string, items: list<array{product_id: int, product_name: string, quantity: string}>}>
     */
    public function getFleetSnapshot(
        int $tenantId,
        ?int $filterCarId = null,
        ?int $filterProductId = null,
        bool $lowStockOnly = false,
        ?string $search = null,
    ): array {
        $carsQuery = Car::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        if ($filterCarId !== null) {
            $carsQuery->where('id', $filterCarId);
        }

        $cars = $carsQuery->get(['id', 'model', 'plate_number']);

        $inventoryRows = Inventory::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('car_id', $cars->pluck('id'))
            ->with(['product:id,item'])
            ->get(['id', 'car_id', 'product_id', 'quantity']);

        $grouped = $inventoryRows->groupBy('car_id');

        $payload = [];

        $searchLower = $search !== null && trim($search) !== '' ? mb_strtolower(trim($search)) : null;
        $threshold = (string) $this->lowStockThreshold;

        foreach ($cars as $car) {
            $carDisplayName = $this->formatCarName($car);
            $carNameMatches = $searchLower === null
                || str_contains(mb_strtolower($carDisplayName), $searchLower);

            /** @var Collection<int, Inventory> $rows */
            $rows = $grouped->get($car->id, collect());
            $items = [];

            foreach ($rows as $inv) {
                if ($filterProductId !== null && (int) $inv->product_id !== $filterProductId) {
                    continue;
                }

                $qty = InventoryMath::normalize((string) $inv->quantity);
                if ($lowStockOnly) {
                    if (InventoryMath::compare($qty, '0') === 0) {
                        continue;
                    }
                    if (! (InventoryMath::compare($qty, '0') > 0
                        && InventoryMath::compare($qty, $threshold) < 0)) {
                        continue;
                    }
                }

                $productName = (string) ($inv->product?->item ?? '');

                if ($searchLower !== null) {
                    $productNameMatches = str_contains(mb_strtolower($productName), $searchLower);
                    if (! $carNameMatches && ! $productNameMatches) {
                        continue;
                    }
                }

                $items[] = [
                    'product_id' => (int) $inv->product_id,
                    'product_name' => $productName,
                    'quantity' => $qty,
                ];
            }

            if ($lowStockOnly && $items === []) {
                continue;
            }

            if ($filterProductId !== null && $items === []) {
                continue;
            }

            if ($searchLower !== null && $items === [] && ! $carNameMatches) {
                continue;
            }

            $payload[] = [
                'car_id' => (int) $car->id,
                'car_name' => $carDisplayName,
                'items' => $items,
            ];
        }

        return $payload;
    }

    /**
     * @return array{
     *     car: \App\Models\Car|null,
     *     snapshot: list<array{product_id: int, product_name: string, quantity: string}>,
     *     transactions: list<array<string, mixed>>
     * }
     */
    public function getDriverInventory(
        int $tenantId,
        int $driverId,
        ?int $latestLimit = null,
    ): array {
        $carId = $this->resolveCurrentCarIdForDriver($tenantId, $driverId);

        if ($carId === null) {
            return [
                'car' => null,
                'snapshot' => [],
                'transactions' => [],
            ];
        }

        $car = Car::query()
            ->where('tenant_id', $tenantId)
            ->find($carId);

        if ($car === null) {
            return [
                'car' => null,
                'snapshot' => [],
                'transactions' => [],
            ];
        }

        $history = $this->getCarInventoryWithHistory($tenantId, $carId, $latestLimit);

        return [
            'car' => $car,
            'snapshot' => $history['snapshot'],
            'transactions' => $history['transactions'],
        ];
    }

    /**
     * @return array{
     *     snapshot: list<array{product_id: int, product_name: string, quantity: string}>,
     *     transactions: list<array<string, mixed>>
     * }
     */
    public function getCarInventoryWithHistory(
        int $tenantId,
        int $carId,
        ?int $latestLimit = null,
    ): array {
        $this->assertTenantOwnsCar($tenantId, $carId);

        $limit = $latestLimit ?? $this->defaultHistoryLimit;

        $snapshotRows = Inventory::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->with(['product:id,item'])
            ->orderBy('product_id')
            ->get(['product_id', 'quantity']);

        $snapshot = $snapshotRows->map(function (Inventory $inv) {
            return [
                'product_id' => (int) $inv->product_id,
                'product_name' => (string) ($inv->product?->item ?? ''),
                'quantity' => InventoryMath::normalize((string) $inv->quantity),
            ];
        })->values()->all();

        $transactions = InventoryTransaction::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->with(['product:id,item'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (InventoryTransaction $tx) {
                $before = $tx->before_qty;
                $after = $tx->after_qty;

                return [
                    'id' => $tx->id,
                    'product_id' => (int) $tx->product_id,
                    'product_name' => (string) ($tx->product?->item ?? ''),
                    'quantity' => InventoryMath::normalize((string) $tx->quantity),
                    'type' => $tx->type,
                    'trip_id' => $tx->trip_id,
                    'sale_id' => $tx->sale_id,
                    'before_qty' => $before !== null ? InventoryMath::normalize((string) $before) : null,
                    'after_qty' => $after !== null ? InventoryMath::normalize((string) $after) : null,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'snapshot' => $snapshot,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array{
     *     low_stock: list<array{car_id: int, car_name: string, product_id: int, product_name: string, quantity: string}>,
     *     zero_stock: list<array{car_id: int, car_name: string, product_id: int, product_name: string, quantity: string}>,
     *     negative_variance_recent: list<array{car_id: int, car_name: string, product_id: int, product_name: string, quantity: string, transaction_id: int, created_at: string|null}>,
     *     repeated_shortages: list<array{car_id: int, car_name: string, product_id: int, product_name: string, events: int}>
     * }
     */
    public function getAlerts(int $tenantId): array
    {
        $since = Carbon::now()->subDays($this->alertsLookbackDays);

        $inventory = Inventory::query()
            ->where('tenant_id', $tenantId)
            ->with(['car:id,model,plate_number', 'product:id,item'])
            ->get(['id', 'car_id', 'product_id', 'quantity']);

        $lowStock = [];
        $zeroStock = [];

        foreach ($inventory as $inv) {
            $qty = InventoryMath::normalize((string) $inv->quantity);
            $base = [
                'car_id' => (int) $inv->car_id,
                'car_name' => $this->formatCarName($inv->car),
                'product_id' => (int) $inv->product_id,
                'product_name' => (string) ($inv->product?->item ?? ''),
                'quantity' => $qty,
            ];

            if (InventoryMath::compare($qty, '0') === 0) {
                $zeroStock[] = $base;
            } elseif (
                InventoryMath::compare($qty, '0') > 0
                && InventoryMath::compare($qty, (string) $this->lowStockThreshold) < 0
            ) {
                $lowStock[] = $base;
            }
        }

        $negativeVariance = InventoryTransaction::query()
            ->where('tenant_id', $tenantId)
            ->where('type', self::TYPE_ADJUSTMENT)
            ->where('quantity', '<', 0)
            ->where('created_at', '>=', $since)
            ->with(['car:id,model,plate_number', 'product:id,item'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (InventoryTransaction $tx) {
                return [
                    'car_id' => (int) $tx->car_id,
                    'car_name' => $this->formatCarName($tx->car),
                    'product_id' => (int) $tx->product_id,
                    'product_name' => (string) ($tx->product?->item ?? ''),
                    'quantity' => InventoryMath::normalize((string) $tx->quantity),
                    'transaction_id' => $tx->id,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $shortageBuckets = InventoryTransaction::query()
            ->selectRaw('car_id, product_id, COUNT(*) as events')
            ->where('tenant_id', $tenantId)
            ->where('type', self::TYPE_ADJUSTMENT)
            ->where('quantity', '<', 0)
            ->where('created_at', '>=', $since)
            ->groupBy('car_id', 'product_id')
            ->having('events', '>=', $this->repeatedShortageMinEvents)
            ->get();

        $repeated = [];

        foreach ($shortageBuckets as $row) {
            $car = Car::query()->where('tenant_id', $tenantId)->find($row->car_id);
            $product = Product::query()->where('tenant_id', $tenantId)->find($row->product_id);

            $repeated[] = [
                'car_id' => (int) $row->car_id,
                'car_name' => $this->formatCarName($car),
                'product_id' => (int) $row->product_id,
                'product_name' => (string) ($product?->item ?? ''),
                'events' => (int) $row->events,
            ];
        }

        return [
            'low_stock' => $lowStock,
            'zero_stock' => $zeroStock,
            'negative_variance_recent' => $negativeVariance,
            'repeated_shortages' => $repeated,
        ];
    }

    private function snapshotQuery(int $tenantId, int $carId, int $productId)
    {
        return Inventory::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->where('product_id', $productId);
    }

    private function persistSnapshotQty(
        int $tenantId,
        int $carId,
        int $productId,
        string $quantity,
        ?int $tripId,
    ): void {
        $qty = InventoryMath::normalize($quantity);

        if (InventoryMath::isNegative($qty)) {
            throw new InvalidArgumentException('Snapshot quantity cannot be negative.');
        }

        $row = Inventory::query()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->where('product_id', $productId)
            ->first();

        if ($row) {
            if ($row->trashed()) {
                $row->restore();
            }

            $row->quantity = $qty;

            if ($tripId !== null) {
                $row->trip_id = $tripId;
            }

            $row->save();

            return;
        }

        Inventory::query()->create([
            'tenant_id' => $tenantId,
            'car_id' => $carId,
            'product_id' => $productId,
            'trip_id' => $tripId,
            'quantity' => $qty,
        ]);
    }

    private function createTransaction(
        int $tenantId,
        int $carId,
        int $productId,
        string $type,
        ?string $quantity,
        ?int $tripId,
        ?int $saleId,
        ?string $beforeQty,
        ?string $afterQty,
        ?string $notes = null,
        ?string $actualQty = null,
        ?string $expectedQty = null,
        ?string $variance = null,
        int $userId,
    ): InventoryTransaction {
        if (! in_array($type, [self::TYPE_LOAD, self::TYPE_SALE, self::TYPE_ADJUSTMENT, self::TYPE_RETURN, self::TYPE_CLOSING_COUNT, self::TYPE_OPENING_BALANCE], true)) {
            throw new InvalidArgumentException('Invalid transaction type for persistence.');
        }

        return InventoryTransaction::query()->create([
            'tenant_id' => $tenantId,
            'car_id' => $carId,
            'product_id' => $productId,
            'trip_id' => $tripId,
            'sale_id' => $saleId,
            'quantity' => $quantity !== null ? InventoryMath::normalize($quantity) : null,
            'type' => $type,
            'created_at' => now(),
            'before_qty' => $beforeQty !== null ? InventoryMath::normalize($beforeQty) : null,
            'after_qty' => $afterQty !== null ? InventoryMath::normalize($afterQty) : null,
            'notes' => $notes,
            'actual_quantity' => $actualQty !== null ? InventoryMath::normalize($actualQty) : null,
            'expected_quantity' => $expectedQty !== null ? InventoryMath::normalize($expectedQty) : null,
            'variance' => $variance !== null ? InventoryMath::normalize($variance) : null,
            'user_id' => $userId,
        ]);
    }

    private function assertNonNegativeSnapshot(int $tenantId, int $carId, int $productId, string $qty): void
    {
        if (InventoryMath::isNegative($qty)) {
            throw new InvalidArgumentException('Inventory quantity cannot be negative.');
        }

        $fresh = $this->getCurrentQuantity($tenantId, $carId, $productId);

        if (InventoryMath::compare($fresh, '0') < 0) {
            throw new InvalidArgumentException('Inventory quantity cannot be negative.');
        }
    }

    private function subtractForDecrease(
        int $tenantId,
        int $carId,
        int $productId,
        string $before,
        string $qty,
    ): string {
        if (InventoryMath::compare($before, $qty) < 0) {
            throw new InsufficientInventoryException($tenantId, $carId, $productId, $before, $qty);
        }

        return InventoryMath::sub($before, $qty);
    }

    private function assertCarCapacityAllowsLoad(
        int $tenantId,
        int $carId,
        int $productId,
        string $afterQty,
    ): void {
        $car = Car::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $carId)
            ->first(['id', 'overall_volume_capacity', 'overall_weight_capacity']);

        if ($car === null) {
            return;
        }

        $volumeCapRaw = $car->overall_volume_capacity;
        $weightCapRaw = $car->overall_weight_capacity;
        $checkVolume = $volumeCapRaw !== null;
        $checkWeight = $weightCapRaw !== null;

        if (! $checkVolume && ! $checkWeight) {
            return;
        }

        $quantities = [];
        $rows = Inventory::query()
            ->where('tenant_id', $tenantId)
            ->where('car_id', $carId)
            ->get(['product_id', 'quantity']);

        foreach ($rows as $row) {
            $quantities[(int) $row->product_id] = InventoryMath::normalize((string) $row->quantity);
        }

        $quantities[$productId] = InventoryMath::normalize($afterQty);

        $productIds = array_keys($quantities);
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get(['id', 'unit_volume', 'unit_weight'])
            ->keyBy('id');

        $totalVolume = '0.000000';
        $totalWeight = '0.000000';

        foreach ($quantities as $pid => $qty) {
            if (InventoryMath::compare($qty, '0') <= 0) {
                continue;
            }

            $product = $products->get($pid);
            if ($product === null) {
                throw new InvalidArgumentException(sprintf('Missing product %d for capacity calculation.', $pid));
            }

            if ($checkVolume) {
                if ($product->unit_volume === null) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Product %d has no unit_volume; cannot verify volume capacity for car %d.',
                            $pid,
                            $carId
                        )
                    );
                }

                $lineVol = InventoryMath::multiply($qty, (string) $product->unit_volume);
                $totalVolume = InventoryMath::add($totalVolume, $lineVol);
            }

            if ($checkWeight) {
                if ($product->unit_weight === null) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Product %d has no unit_weight; cannot verify weight capacity for car %d.',
                            $pid,
                            $carId
                        )
                    );
                }

                $lineWt = InventoryMath::multiply($qty, (string) $product->unit_weight);
                $totalWeight = InventoryMath::add($totalWeight, $lineWt);
            }
        }

        if ($checkVolume) {
            $cap = InventoryMath::normalize((string) $volumeCapRaw);
            if (InventoryMath::compare($totalVolume, $cap) > 0) {
                throw new CarCapacityExceededException(
                    $tenantId,
                    $carId,
                    'volume',
                    $cap,
                    $totalVolume
                );
            }
        }

        if ($checkWeight) {
            $cap = InventoryMath::normalize((string) $weightCapRaw);
            if (InventoryMath::compare($totalWeight, $cap) > 0) {
                throw new CarCapacityExceededException(
                    $tenantId,
                    $carId,
                    'weight',
                    $cap,
                    $totalWeight
                );
            }
        }
    }

    /**
     * @param  list<array{product_id: int|string, quantity: string|int|float}>|list<InventoryOperationData>  $items
     * @return list<InventoryOperationData>
     */
    private function normalizeItemInputs(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if ($item instanceof InventoryOperationData) {
                $out[] = $item;

                continue;
            }

            $out[] = InventoryOperationData::fromArray([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return $out;
    }

    private function requireNonNegativeQuantity(string|int|float $quantity): string
    {
        $q = InventoryMath::normalize($quantity);

        if (InventoryMath::compare($q, '0') < 0) {
            throw new InvalidArgumentException('Quantity must be zero or greater.');
        }

        return $q;
    }

    private function assertTenantOwnsCar(int $tenantId, int $carId): void
    {
        $exists = Car::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $carId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Car does not belong to this tenant.');
        }
    }

    private function assertTenantOwnsProduct(int $tenantId, int $productId): void
    {
        $exists = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('Product does not belong to this tenant.');
        }
    }

    private function formatCarName(?Car $car): string
    {
        if ($car === null) {
            return '';
        }

        $model = trim((string) ($car->model ?? ''));
        $plate = trim((string) ($car->plate_number ?? ''));

        if ($model !== '' && $plate !== '') {
            return $model.' ('.$plate.')';
        }

        return $model !== '' ? $model : $plate;
    }

    private function resolveCurrentCarIdForDriver(int $tenantId, int $driverId): ?int
    {
        $activeCarId = Trip::query()
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereNull('end_date')
            ->orderByDesc('id')
            ->value('car_id');

        if ($activeCarId) {
            return (int) $activeCarId;
        }

        $lastCarId = Trip::query()
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->orderByDesc('id')
            ->value('car_id');

        return $lastCarId ? (int) $lastCarId : null;
    }
}
