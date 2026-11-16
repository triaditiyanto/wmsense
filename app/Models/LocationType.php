<?php

namespace App\Models;

use App\Enums\LocationFunction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Level 1 hierarki lokasi. */
class LocationType extends Model
{
    protected $table = 'master_location_type';

    protected $fillable = ['code', 'name', 'location_function', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'location_function' => LocationFunction::class,
            'is_active' => 'boolean',
        ];
    }

    public function locationClasses(): HasMany
    {
        return $this->hasMany(LocationClass::class);
    }

    /** Semua lokasi bertipe ini, lewat location class. */
    public function locations(): HasManyThrough
    {
        return $this->hasManyThrough(Location::class, LocationClass::class);
    }
}
