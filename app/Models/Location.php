<?php

namespace App\Models;

use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\TemperatureClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level 3 hierarki lokasi (bin fisik).
 * Hierarki: LocationType → LocationClass → Location.
 */
class Location extends Model
{
    protected $table = 'master_location';

    protected $fillable = [
        'warehouse_id', 'zone_id', 'location_class_id', 'code', 'status',
        'aisle', 'bay', 'level', 'position', 'pick_sequence', 'max_lpn', 'max_weight_kg', 'max_volume_m3',
        'temperature_class', 'attributes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LocationStatus::class,
            'temperature_class' => TemperatureClass::class,
            'level' => 'integer',
            'pick_sequence' => 'integer',
            'max_lpn' => 'integer',
            'max_weight_kg' => 'decimal:3',
            'max_volume_m3' => 'decimal:6',
            'attributes' => 'array',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function locationClass(): BelongsTo
    {
        return $this->belongsTo(LocationClass::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Fungsi lokasi (RECEIVING, STORAGE, ...) dari location type.
     * Butuh eager load: Location::with('locationClass.locationType').
     */
    public function locationFunction(): LocationFunction
    {
        return $this->locationClass->locationType->location_function;
    }

    /**
     * Kapasitas efektif: nilai di lokasi, atau warisan dari kelas lokasi. NULL = tidak dibatasi.
     * Butuh relasi locationClass sudah di-load.
     *
     * @return array{max_lpn: ?int, max_weight_kg: ?float, max_volume_m3: ?float}
     */
    public function capacity(): array
    {
        $class = $this->locationClass;
        $pick = fn ($own, $inherited) => $own !== null ? $own : $inherited;
        $weight = $pick($this->max_weight_kg, $class->max_weight_kg);
        $volume = $pick($this->max_volume_m3, $class->max_volume_m3);

        return [
            'max_lpn' => $pick($this->max_lpn, $class->max_lpn),
            'max_weight_kg' => $weight !== null ? (float) $weight : null,
            'max_volume_m3' => $volume !== null ? (float) $volume : null,
        ];
    }

    /** Kelas suhu efektif: milik lokasi, atau warisan dari zona. */
    public function effectiveTemperatureClass(): TemperatureClass
    {
        return $this->temperature_class ?? $this->zone->temperature_class;
    }
}
