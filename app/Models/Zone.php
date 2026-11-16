<?php

namespace App\Models;

use App\Enums\TemperatureClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $table = 'master_zone';

    protected $fillable = [
        'warehouse_id', 'code', 'name', 'temperature_class', 'attributes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'temperature_class' => TemperatureClass::class,
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
