<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetRequest extends Model
{
    protected $table = 'requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'driver_id',
        'user_id',
        'status',
        'notes',
        'maintenance_requested',
        'fuel_requested',
        'litre_cost',
        'invoice_image',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        $tenantId = request()->attributes->get('tenant_id');

        if ($tenantId === null) {
            abort(404);
        }

        $field ??= $this->getRouteKeyName();

        $model = static::query()
            ->whereHas('driver', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where($field, $value)
            ->first();

        return $model ?? abort(404);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->whereHas('driver', fn ($q) => $q->where('tenant_id', $tenantId));
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
