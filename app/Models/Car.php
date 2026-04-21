<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTenantRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory, ResolvesTenantRouteBinding, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'model',
        'plate_number',
        'overall_volume_capacity',
        'overall_weight_capacity',
        'current_fuel',
        'fuel_efficiency',
        'color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_volume_capacity' => 'decimal:3',
            'overall_weight_capacity' => 'decimal:3',
            'current_fuel' => 'decimal:3',
            'fuel_efficiency' => 'decimal:4',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
