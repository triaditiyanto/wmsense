<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUom extends Model
{
    protected $table = 'master_item_uom';

    protected $fillable = [
        'item_id', 'uom_id', 'conversion_factor', 'barcode',
        'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        'is_default_inbound', 'is_default_outbound',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'weight_kg' => 'decimal:4',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'is_default_inbound' => 'boolean',
            'is_default_outbound' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
