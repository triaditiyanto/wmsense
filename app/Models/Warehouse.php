<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $table = 'master_warehouse';

    protected $fillable = ['code', 'name', 'address', 'timezone', 'profile', 'is_active'];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
