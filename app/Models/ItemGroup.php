<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Level 1 hierarki item. */
class ItemGroup extends Model
{
    protected $table = 'master_item_group';

    protected $fillable = ['code', 'name', 'description', 'attributes', 'is_active'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function itemClasses(): HasMany
    {
        return $this->hasMany(ItemClass::class);
    }

    /** Semua item dalam group ini, lewat item class. */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(Item::class, ItemClass::class);
    }
}
