<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenant';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subscription_plan',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
