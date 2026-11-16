<?php

namespace App\Models;

use App\Enums\RotationRule;
use App\Enums\TemperatureClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level 3 hierarki item. Hierarki: ItemGroup → ItemClass → Item.
 */
class Item extends Model
{
    protected $table = 'master_item';

    protected $fillable = [
        'owner_id', 'item_class_id', 'base_uom_id', 'sku', 'name', 'description', 'barcode',
        'rotation_rule', 'temperature_class', 'is_lot_tracked', 'is_expiry_tracked',
        'shelf_life_days', 'min_shelf_life_days', 'is_hazardous',
        'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        'attributes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rotation_rule' => RotationRule::class,
            'temperature_class' => TemperatureClass::class,
            'is_lot_tracked' => 'boolean',
            'is_expiry_tracked' => 'boolean',
            'shelf_life_days' => 'integer',
            'min_shelf_life_days' => 'integer',
            'is_hazardous' => 'boolean',
            'weight_kg' => 'decimal:4',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function itemClass(): BelongsTo
    {
        return $this->belongsTo(ItemClass::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function uoms(): HasMany
    {
        return $this->hasMany(ItemUom::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Faktor konversi UOM -> base UOM (string decimal), null bila UOM tidak berlaku untuk item ini.
     * Base UOM selalu berlaku dengan faktor 1. Butuh relasi `uoms` sudah di-load.
     */
    public function conversionFactor(int $uomId): ?string
    {
        if ($uomId === $this->base_uom_id) {
            return '1';
        }

        return $this->uoms->firstWhere('uom_id', $uomId)?->conversion_factor;
    }

    /**
     * Daftar pilihan UOM untuk dropdown: [{id, code, factor}], base UOM paling atas.
     * Butuh relasi `baseUom` dan `uoms.uom` sudah di-load.
     */
    public function uomOptions(): array
    {
        $options = [['id' => $this->base_uom_id, 'code' => $this->baseUom->code, 'factor' => '1']];
        foreach ($this->uoms as $iu) {
            if ($iu->uom_id !== $this->base_uom_id) {
                $options[] = ['id' => $iu->uom_id, 'code' => $iu->uom->code, 'factor' => rtrim(rtrim($iu->conversion_factor, '0'), '.')];
            }
        }

        return $options;
    }
}
