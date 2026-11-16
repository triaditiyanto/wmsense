<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Enums\MovementType;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ledger append-only. Koreksi = movement baru (ADJUST_IN / ADJUST_OUT), bukan edit.
 */
class InventoryMovement extends Model
{
    protected $table = 'o_inventory_movement';

    // Tabel ini hanya punya created_at
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'status' => InventoryStatus::class,
            'expiry_date' => 'date:Y-m-d',
            'qty_on_hand_change' => 'decimal:4',
            'qty_allocated_change' => 'decimal:4',
            'qty_hold_change' => 'decimal:4',
            'qty_on_hand_after' => 'decimal:4',
            'qty_allocated_after' => 'decimal:4',
            'qty_hold_after' => 'decimal:4',
            'seq' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ImmutableRecordException('inventory_movements bersifat append-only dan tidak boleh diubah.');
        });

        static::deleting(function () {
            throw new ImmutableRecordException('inventory_movements bersifat append-only dan tidak boleh dihapus.');
        });
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Dokumen sumber: Receipt, Task, Order, dll. */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
