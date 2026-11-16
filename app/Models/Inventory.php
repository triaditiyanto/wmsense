<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saldo stok per lokasi. JANGAN ubah qty langsung dari controller:
 * semua perubahan lewat InventoryService (DB::transaction + lockForUpdate + movement).
 */
class Inventory extends Model
{
    protected $table = 'o_inventory';

    // stock_key diisi otomatis; qty_available adalah generated column (read-only)
    protected $guarded = ['id', 'stock_key', 'qty_available'];

    protected function casts(): array
    {
        return [
            'status' => InventoryStatus::class,
            'expiry_date' => 'date:Y-m-d',
            'received_at' => 'datetime',
            'qty_on_hand' => 'decimal:4',
            'qty_allocated' => 'decimal:4',
            'qty_hold' => 'decimal:4',
            'qty_available' => 'decimal:4',
            'qty_incoming' => 'decimal:4',
        ];
    }

    /**
     * Model event: "saving" jalan sebelum INSERT maupun UPDATE.
     * Mirip trigger, tapi di level PHP.
     */
    protected static function booted(): void
    {
        static::saving(function (Inventory $inv) {
            $inv->stock_key = static::makeStockKey(
                $inv->item_id,
                $inv->owner_id,
                $inv->lot_no,
                $inv->expiry_date,
                $inv->lpn_id,
                $inv->status ?? InventoryStatus::AVAILABLE,
            );
        });
    }

    /**
     * Dipakai juga oleh service untuk mencari baris yang sudah ada
     * (WHERE location_id = ? AND stock_key = ?) sebelum membuat baris baru.
     */
    public static function makeStockKey(
        int $itemId,
        int $ownerId,
        ?string $lotNo,
        DateTimeInterface|string|null $expiryDate,
        ?int $lpnId,
        InventoryStatus|string $status,
    ): string {
        $exp = $expiryDate instanceof DateTimeInterface
            ? $expiryDate->format('Y-m-d')
            : ($expiryDate ? date('Y-m-d', strtotime($expiryDate)) : '');

        return hash('sha256', implode('|', [
            $itemId,
            $ownerId,
            trim((string) $lotNo),
            $exp,
            (string) $lpnId,
            $status instanceof InventoryStatus ? $status->value : $status,
        ]));
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

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
