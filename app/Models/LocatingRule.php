<?php

namespace App\Models;

use App\Enums\LocatingScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aturan locating untuk satu Item / Item Class / Item Group di satu gudang (Master > Item Locating).
 * Dipakai LocatingService saat menyarankan lokasi putaway. Resolusi: LocatingRuleResolver.
 */
class LocatingRule extends Model
{
    public const LEVEL_LOW = 'LOW';
    public const LEVEL_HIGH = 'HIGH';

    protected $table = 'master_locating_rule';

    protected $fillable = [
        'warehouse_id', 'scope_type', 'scope_id', 'level_preference', 'prefer_consolidate', 'strict',
        'is_active', 'notes', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => LocatingScope::class,
            'prefer_consolidate' => 'boolean',
            'strict' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(LocatingRuleTarget::class)->orderBy('seq');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Prioritas lokasi menurut aturan: 1 = target pertama, ... ; null = tidak masuk target mana pun.
     * Aturan tanpa target = semua lokasi prioritas 1 (hanya opsi level / konsolidasi yang berlaku).
     */
    public function priorityFor(Location $location): ?int
    {
        if ($this->targets->isEmpty()) {
            return 1;
        }
        foreach ($this->targets as $i => $t) {
            if ($t->matches($location)) {
                return $i + 1;
            }
        }

        return null;
    }
}
