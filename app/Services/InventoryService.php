<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * SATU-SATUNYA pintu untuk mengubah o_inventory.
 * Aturan: selalu di dalam DB::transaction, baris stok dikunci (SELECT ... FOR UPDATE),
 * dan setiap perubahan menulis satu baris ledger o_inventory_movement.
 */
class InventoryService
{
    /**
     * Tambah (delta positif) atau kurangi (delta negatif) qty_on_hand satu baris stok.
     * Baris dibuat otomatis bila belum ada (hanya untuk penambahan).
     */
    public function changeOnHand(
        StockAttributes $attr,
        string|int|float $delta,
        MovementType $type,
        MovementContext $ctx,
        ?DateTimeInterface $receivedAt = null,
    ): Inventory {
        return $this->change($attr, $delta, 0, $type, $ctx, $receivedAt);
    }

    /**
     * Ubah qty_on_hand dan/atau qty_allocated satu baris stok sekaligus (satu movement).
     *   alokasi    : change(attr, 0, +qty, ALLOCATE)      - butuh baris sudah ada
     *   lepas      : change(attr, 0, -qty, DEALLOCATE)
     *   pick keluar: change(attr, -qty, -qty, PICK_OUT)   - fisik & reservasi turun bersama
     *   pick masuk : change(attr, +qty, +qty, PICK_IN)    - di staging tetap tereservasi untuk shipment-nya
     *   kirim      : change(attr, -qty, -qty, SHIP)
     * Baris dibuat otomatis hanya bila qty_on_hand bertambah.
     */
    public function change(
        StockAttributes $attr,
        string|int|float $onHandDelta,
        string|int|float $allocatedDelta,
        MovementType $type,
        MovementContext $ctx,
        ?DateTimeInterface $receivedAt = null,
        string|int|float $holdDelta = 0,
    ): Inventory {
        $onHandDelta = (string) $onHandDelta;
        $allocatedDelta = (string) $allocatedDelta;
        $holdDelta = (string) $holdDelta;
        if (Qty::cmp($onHandDelta, 0) === 0 && Qty::cmp($allocatedDelta, 0) === 0 && Qty::cmp($holdDelta, 0) === 0) {
            throw new LogicException('Perubahan stok tidak boleh 0.');
        }

        // Transaksi bersarang aman: Laravel memakai SAVEPOINT bila sudah ada transaksi luar
        return DB::transaction(function () use ($attr, $onHandDelta, $allocatedDelta, $holdDelta, $type, $ctx, $receivedAt) {
            $isIncrease = Qty::cmp($onHandDelta, 0) > 0;
            $inv = $this->lockRow($attr, $isIncrease, $receivedAt);

            if (! $inv) {
                throw new BusinessException('Stok tidak ditemukan di lokasi/LPN tersebut (mungkin sudah dipindah).');
            }

            $newOnHand = Qty::add($inv->qty_on_hand, $onHandDelta);
            $newAllocated = Qty::add($inv->qty_allocated, $allocatedDelta);
            $newHold = Qty::add($inv->qty_hold, $holdDelta);
            if (Qty::cmp($newHold, 0) < 0) {
                throw new BusinessException('Qty hold yang dilepas melebihi yang di-hold ('.Qty::fmt($inv->qty_hold).').');
            }
            if (Qty::cmp($newOnHand, 0) < 0) {
                throw new BusinessException('Stok tidak cukup: tersedia '.Qty::fmt($inv->qty_on_hand).', diminta '.Qty::fmt(Qty::sub(0, $onHandDelta)).'.');
            }
            if (Qty::cmp($newAllocated, 0) < 0) {
                throw new BusinessException('Qty alokasi yang dilepas melebihi yang teralokasi ('.Qty::fmt($inv->qty_allocated).').');
            }
            if (Qty::cmp($newOnHand, Qty::add($newAllocated, $newHold)) < 0) {
                throw new BusinessException(Qty::cmp($allocatedDelta, 0) > 0 || Qty::cmp($holdDelta, 0) > 0
                    ? 'Stok tersedia tidak cukup untuk '.(Qty::cmp($holdDelta, 0) > 0 ? 'di-hold' : 'dialokasikan').' (tersedia '.Qty::fmt($inv->qty_available).').'
                    : 'Stok sudah dialokasikan / di-hold, tidak bisa dikurangi.');
            }

            $inv->qty_on_hand = $newOnHand;
            $inv->qty_allocated = $newAllocated;
            $inv->qty_hold = $newHold;
            $inv->save();

            $this->writeMovement($inv, $type, $ctx, onHandChange: $onHandDelta, allocatedChange: $allocatedDelta, holdChange: $holdDelta);

            return $inv;
        });
    }

    /** Apakah LPN masih berisi stok fisik. */
    public function lpnHasStock(int $lpnId): bool
    {
        return Inventory::where('lpn_id', $lpnId)->where('qty_on_hand', '>', 0)->exists();
    }

    /** Cari & kunci baris stok; buat baru bila belum ada dan diizinkan. */
    private function lockRow(StockAttributes $a, bool $createIfMissing, ?DateTimeInterface $receivedAt): ?Inventory
    {
        $find = fn () => Inventory::query()
            ->where('location_id', $a->locationId)
            ->where('stock_key', $a->stockKey())
            ->lockForUpdate()
            ->first();

        if ($inv = $find()) {
            return $inv;
        }
        if (! $createIfMissing) {
            return null;
        }

        try {
            // Savepoint: bila transaksi lain membuat baris yang sama bersamaan (bentrok unique),
            // hanya savepoint ini yang batal, transaksi utama tetap jalan lalu baris yang ada dikunci.
            DB::transaction(fn () => Inventory::create([
                'warehouse_id' => $a->warehouseId,
                'location_id' => $a->locationId,
                'item_id' => $a->itemId,
                'owner_id' => $a->ownerId,
                'lot_no' => $a->lotNo,
                'expiry_date' => $a->expiryDate,
                'lpn_id' => $a->lpnId,
                'status' => $a->status,
                'received_at' => $receivedAt ?? now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // sudah dibuat transaksi lain -> lanjut ambil
        }

        return $find();
    }

    private function writeMovement(Inventory $inv, MovementType $type, MovementContext $ctx, string $onHandChange = '0', string $allocatedChange = '0', string $holdChange = '0'): void
    {
        InventoryMovement::create([
            'warehouse_id' => $inv->warehouse_id,
            'inventory_id' => $inv->id,
            'location_id' => $inv->location_id,
            'item_id' => $inv->item_id,
            'owner_id' => $inv->owner_id,
            'lot_no' => $inv->lot_no,
            'expiry_date' => $inv->expiry_date,
            'lpn_id' => $inv->lpn_id,
            'status' => $inv->status,
            'type' => $type,
            'qty_on_hand_change' => $onHandChange,
            'qty_allocated_change' => $allocatedChange,
            'qty_hold_change' => $holdChange,
            'qty_on_hand_after' => $inv->qty_on_hand,
            'qty_allocated_after' => $inv->qty_allocated,
            'qty_hold_after' => $inv->qty_hold,
            'reference_type' => $ctx->reference?->getMorphClass(),
            'reference_id' => $ctx->reference?->getKey(),
            'reason_code' => $ctx->reasonCode,
            'notes' => $ctx->notes,
            'idempotency_key' => $ctx->idempotencyKey,
            'seq' => $ctx->seq,
            'user_id' => $ctx->userId,
        ]);
    }
}
