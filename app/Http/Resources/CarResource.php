<?php

namespace App\Http\Resources;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Car */
class CarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'model' => $this->model,
            'plate_number' => $this->plate_number,
            'overall_volume_capacity' => $this->overall_volume_capacity,
            'overall_weight_capacity' => $this->overall_weight_capacity,
            'current_fuel' => $this->current_fuel,
            'fuel_efficiency' => $this->fuel_efficiency,
            'color' => $this->color,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
