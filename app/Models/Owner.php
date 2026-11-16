<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    protected $table = 'master_owner';

    protected $fillable = ['code', 'name', 'contact', 'attributes', 'is_active'];

    protected function casts(): array
    {
        return [
            'contact' => 'array',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
