<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Level 2 hierarki item, di bawah item group. */
class ItemClass extends Model
{
    protected $table = 'master_item_class';

    protected $fillable = ['item_group_id', 'code', 'name', 'description', 'attributes', 'is_active'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
