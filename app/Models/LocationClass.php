<?php

namespace App\Models;

use App\Enums\StorageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Level 2 hierarki lokasi: template fisik, kapasitas & aturan mixing. */
class LocationClass extends Model
{
    protected $table = 'master_location_class';

    protected $fillable = [
        'location_type_id', 'code', 'name', 'storage_type',
        'length_cm', 'width_cm', 'height_cm',
        'max_weight_kg', 'max_volume_m3', 'max_lpn',
        'allow_mixed_items', 'allow_mixed_lots',
        'attributes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'storage_type' => StorageType::class,
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'max_weight_kg' => 'decimal:3',
            'max_volume_m3' => 'decimal:6',
            'max_lpn' => 'integer',
            'allow_mixed_items' => 'boolean',
            'allow_mixed_lots' => 'boolean',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function locationType(): BelongsTo
    {
        return $this->belongsTo(LocationType::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
