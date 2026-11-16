<?php

namespace App\Models;

use App\Enums\PalletMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jenis / spesifikasi pallet. */
class Pallet extends Model
{
    protected $table = 'master_pallet';

    protected $fillable = [
        'code', 'name', 'material', 'length_cm', 'width_cm', 'height_cm',
        'tare_weight_kg', 'max_load_kg', 'max_stack_height_cm', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'material' => PalletMaterial::class,
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'tare_weight_kg' => 'decimal:3',
            'max_load_kg' => 'decimal:3',
            'max_stack_height_cm' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function lpns(): HasMany
    {
        return $this->hasMany(Lpn::class);
    }
}
