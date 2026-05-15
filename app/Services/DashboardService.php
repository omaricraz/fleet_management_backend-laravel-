<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FleetRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;

final class DashboardService
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Tenant-scoped snapshot for dashboards. Counts honour model soft-delete scopes where applicable.
     *
     * Sales `today` uses `created_at` between start and end of today in `config('app.timezone')`.
     * `rolling_7_days` uses `created_at >= now() - 7 days` (inclusive of the boundary instant).
     *
     * Inventory alert counts match {@see InventoryService::getAlerts} (same thresholds as GET `inventory/alerts`).
     *
     * @return array<string, mixed>
     */
    public function summarize(int $tenantId): array
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $sevenDaysAgo = $now->copy()->subDays(7);

        $alerts = $this->inventory->getAlerts($tenantId);

        /** @var array<string, mixed> */
        return [
            'as_of' => $now->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'counts' => [
                'zones' => Zone::query()->where('tenant_id', $tenantId)->count(),
                'cars' => Car::query()->where('tenant_id', $tenantId)->count(),
                'drivers' => Driver::query()->where('tenant_id', $tenantId)->count(),
                'customers' => Customer::query()->where('tenant_id', $tenantId)->count(),
                'products' => Product::query()->where('tenant_id', $tenantId)->count(),
                'tenant_users' => User::query()->where('tenant_id', $tenantId)->where('is_platform_admin', false)->count(),
            ],
            'trips' => $this->tripSummary($tenantId, $sevenDaysAgo),
            'sales' => [
                'today' => $this->saleAggregate($tenantId, $todayStart, $todayEnd),
                'rolling_7_days' => $this->saleAggregate($tenantId, $sevenDaysAgo, null),
            ],
            'requests' => $this->requestSummary($tenantId),
            'inventory_alerts' => [
                'low_stock_car_product_lines' => count($alerts['low_stock'] ?? []),
                'zero_stock_car_product_lines' => count($alerts['zero_stock'] ?? []),
                'negative_closing_variance_rows' => count($alerts['negative_variance_recent'] ?? []),
                'repeated_shortage_patterns' => count($alerts['repeated_shortages'] ?? []),
            ],
        ];
    }

    /**
     * `active_in_progress` matches {@see TripService::findCurrentTripForDriver}: status active with no `end_date`.
     *
     * @return array<string, mixed>
     */
    private function tripSummary(int $tenantId, Carbon $sevenDaysAgo): array
    {
        $byStatusRaw = Trip::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->all();

        /** @var array<string, int> $by_status */
        $byStatus = [];

        foreach ($byStatusRaw as $statusKey => $count) {
            $byStatus[(string) $statusKey] = (int) $count;
        }

        return [
            'by_status' => $byStatus,
            'active_in_progress' => Trip::query()
                ->where('tenant_id', $tenantId)
                ->where('status', TripService::STATUS_ACTIVE)
                ->whereNull('end_date')
                ->count(),
            'completed_last_7_days' => Trip::query()
                ->where('tenant_id', $tenantId)
                ->where('status', TripService::STATUS_CLOSED)
                ->whereNotNull('end_date')
                ->where('end_date', '>=', $sevenDaysAgo)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestSummary(int $tenantId): array
    {
        $byStatusRaw = FleetRequest::query()
            ->forTenant($tenantId)
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->all();

        /** @var array<string, int> $by_status */
        $byStatus = [];

        foreach ($byStatusRaw as $statusKey => $count) {
            $byStatus[(string) $statusKey] = (int) $count;
        }

        return ['by_status' => $byStatus];
    }

    /**
     * @return array{sale_count: int, quantity_sum: string, revenue_sum: string}
     */
    private function saleAggregate(int $tenantId, Carbon $fromInclusive, ?Carbon $toInclusive): array
    {
        $base = Sale::query()->where('tenant_id', $tenantId)->where('created_at', '>=', $fromInclusive);

        if ($toInclusive !== null) {
            $base->where('created_at', '<=', $toInclusive);
        }

        /** @var \stdClass|null $row */
        $row = $base->clone()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as sale_count, COALESCE(SUM(quantity), 0) as quantity_sum, COALESCE(SUM(total_price), 0) as revenue_sum'
            )
            ->first();

        return [
            'sale_count' => (int) ($row->sale_count ?? 0),
            'quantity_sum' => self::normalizeNumericString((string) ($row->quantity_sum ?? '0')),
            'revenue_sum' => self::normalizeNumericString((string) ($row->revenue_sum ?? '0')),
        ];
    }

    private static function normalizeNumericString(string $value): string
    {
        $v = trim($value);

        return $v === '' ? '0' : $v;
    }
}
