<?php

namespace App\Models;

use App\Enums\LocatingTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu area tujuan (berurutan) dalam aturan locating. */
class LocatingRuleTarget extends Model
{
    protected $table = 'master_locating_rule_target';

    protected $fillable = [
        'locating_rule_id', 'seq', 'target_type', 'zone_id', 'aisle', 'location_class_id', 'location_id', 'level_min', 'level_max',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => LocatingTarget::class,
            'seq' => 'integer',
            'level_min' => 'integer',
            'level_max' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LocatingRule::class, 'locating_rule_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function locationClass(): BelongsTo
    {
        return $this->belongsTo(LocationClass::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Apakah lokasi termasuk area ini (termasuk rentang level). */
    public function matches(Location $l): bool
    {
        $inArea = match ($this->target_type) {
            LocatingTarget::ZONE => $l->zone_id === $this->zone_id,
            LocatingTarget::AISLE => $l->zone_id === $this->zone_id && (string) $l->aisle === (string) $this->aisle,
            LocatingTarget::LOCATION_CLASS => $l->location_class_id === $this->location_class_id,
            LocatingTarget::LOCATION => $l->id === $this->location_id,
        };
        if (! $inArea) {
            return false;
        }
        $level = (int) ($l->level ?? 1);

        return ($this->level_min === null || $level >= $this->level_min)
            && ($this->level_max === null || $level <= $this->level_max);
    }
}
