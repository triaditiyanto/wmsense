<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\LpnStatus;
use App\Enums\MovementType;
use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmationRequiredException;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Location;
use App\Models\Lpn;
use App\Models\User;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kontrol stok manual: pindah lokasi (MOVE), koreksi (ADJUST) dan hold / release.
 * Semua perubahan lewat InventoryService (lock + ledger). Alasan disimpan di reason_code + notes movement.
 */
class StockControlService
{
    public const ADJUST_REASONS = ['COUNT' => 'Hasil stock opname', 'DAMAGE' => 'Barang rusak', 'LOST' => 'Barang hilang', 'FOUND' => 'Barang temuan', 'OTHER' => 'Lainnya'];
    public const HOLD_REASONS = ['QC' => 'Menunggu pemeriksaan QC', 'RECALL' => 'Recall produk', 'INVESTIGATION' => 'Investigasi selisih', 'OTHER' => 'Lainnya'];

    /** Lokasi tujuan pindah stok yang boleh dipakai. */
    private const MOVE_TARGETS = [LocationFunction::STORAGE, LocationFunction::PICK_FACE, LocationFunction::QC, LocationFunction::QUARANTINE];
    /** Stok di lokasi ini tidak boleh dipindah manual (punya alurnya sendiri: putaway / picking / dispatch). */
    private const MOVE_SOURCES_BLOCKED = [LocationFunction::RECEIVING, LocationFunction::STAGING_OUT, LocationFunction::SHIPPING];

    public function __construct(private InventoryService $inventory, private LocatingService $locating) {}

    // ------------------------------------------------------------------ pencarian

    /**
     * Cari baris stok untuk layar kontrol & cek stok mobile.
     * q cocok: kode lokasi / no. LPN / SKU / nama / lot.
     */
    public function search(?int $warehouseId, ?string $q, int $limit = 60, bool $onlyOnHand = true): Collection
    {
        $q = trim((string) $q);

        return Inventory::query()
            ->with(['item.baseUom', 'location.zone', 'lpn', 'owner'])
            ->when($onlyOnHand, fn ($w) => $w->where('o_inventory.qty_on_hand', '>', 0))
            ->when($warehouseId, fn ($w) => $w->where('o_inventory.warehouse_id', $warehouseId))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $w->where(fn ($s) => $s
                    ->whereHas('location', fn ($l) => $l->where('code', 'like', $like))
                    ->orWhereHas('lpn', fn ($l) => $l->where('lpn_no', 'like', $like))
                    ->orWhereHas('item', fn ($i) => $i->where('sku', 'like', $like)->orWhere('name', 'like', $like)->orWhere('barcode', 'like', $like))
                    ->orWhere('o_inventory.lot_no', 'like', $like));
            })
            ->orderBy('o_inventory.location_id')->orderBy('o_inventory.id')
            ->limit($limit)->get();
    }

    // ------------------------------------------------------------------ pindah lokasi

    /**
     * Pindah stok. Baris ber-LPN = pindah seluruh LPN (isi LPN ikut). Stok lepas = pindah $qty (null = semua yang bebas).
     * Peringatan (suhu, penuh, campur item) butuh $confirm + hak INV_MOVE:approve.
     */
    public function move(int $inventoryId, int $toLocationId, ?string $qty, User $user, bool $confirm = false, ?string $notes = null): array
    {
        return DB::transaction(function () use ($inventoryId, $toLocationId, $qty, $user, $confirm, $notes) {
            $row = Inventory::with(['item.baseUom', 'location.locationClass.locationType'])->lockForUpdate()->find($inventoryId);
            if (! $row || Qty::cmp($row->qty_on_hand, 0) <= 0) {
                throw new BusinessException('Stok tidak ditemukan (mungkin sudah dipindah).');
            }
            $from = $row->location;
            if (in_array($from->locationFunction(), self::MOVE_SOURCES_BLOCKED, true)) {
                throw new BusinessException("Stok di {$from->code} ({$from->locationFunction()->value}) tidak dipindah manual — gunakan Putaway / Picking / Dispatch.");
            }
            $to = Location::with(['zone', 'locationClass.locationType'])->findOrFail($toLocationId);
            $this->assertTarget($to, $row->warehouse_id, $from->id);

            $lpn = $row->lpn_id ? Lpn::whereKey($row->lpn_id)->lockForUpdate()->firstOrFail() : null;
            $rows = $lpn
                ? Inventory::with('item.baseUom')->where('lpn_id', $lpn->id)->where('location_id', $from->id)->where('qty_on_hand', '>', 0)->orderBy('id')->lockForUpdate()->get()
                : collect([$row]);

            if ($lpn) {
                foreach ($rows as $r) {
                    if (Qty::cmp($r->qty_allocated, 0) > 0 || Qty::cmp($r->qty_hold, 0) > 0) {
                        throw new BusinessException("LPN {$lpn->lpn_no} berisi stok teralokasi / di-hold ({$r->item->sku}), tidak bisa dipindah.");
                    }
                }
                $moveQty = null;
            } else {
                $moveQty = $qty === null || $qty === '' ? $row->qty_available : (string) $qty;
                if (Qty::cmp($moveQty, 0) <= 0) {
                    throw new BusinessException('Qty pindah harus lebih dari 0.');
                }
                if (Qty::cmp($moveQty, $row->qty_available) > 0) {
                    throw new BusinessException('Qty melebihi stok bebas ('.Qty::fmt($row->qty_available).' '.$row->item->baseUom->code.'; sisanya teralokasi / di-hold).');
                }
            }

            $warnings = $this->locating->check($to, $rows);
            if (! $lpn) { // stok lepas tidak memakai slot LPN
                $warnings = array_values(array_filter($warnings, fn ($w) => ! str_contains($w, 'penuh')));
            }
            if ($warnings) {
                $canConfirm = $user->hasAccess('INV_MOVE', 'approve');
                if (! $confirm) {
                    throw new ConfirmationRequiredException($warnings, $canConfirm);
                }
                if (! $canConfirm) {
                    throw new BusinessException('Butuh persetujuan SPV: '.implode(' ', $warnings));
                }
            }

            $key = 'MOVE';
            $seq = 0;
            foreach ($rows as $r) {
                $q = $lpn ? $r->qty_on_hand : $moveQty;
                $ctx = fn () => new MovementContext(null, $user->id, null, ++$seq, $key, $notes);
                $this->inventory->changeOnHand(StockAttributes::fromInventory($r), Qty::sub(0, $q), MovementType::MOVE_OUT, $ctx());
                $this->inventory->changeOnHand(
                    new StockAttributes($r->warehouse_id, $to->id, $r->item_id, $r->owner_id, $r->lot_no, $r->expiry_date?->format('Y-m-d'), $r->lpn_id, $r->status),
                    $q, MovementType::MOVE_IN, $ctx(), $r->received_at,
                );
            }
            if ($lpn) {
                $lpn->update(['location_id' => $to->id]);
            }

            return [
                'message' => $lpn
                    ? "LPN {$lpn->lpn_no} dipindah {$from->code} → {$to->code}."
                    : "{$row->item->sku} ".Qty::fmt($moveQty)." {$row->item->baseUom->code} dipindah {$from->code} → {$to->code}.",
            ];
        });
    }

    private function assertTarget(Location $to, int $warehouseId, int $fromId): void
    {
        if ($to->warehouse_id !== $warehouseId) {
            throw new BusinessException("Lokasi {$to->code} ada di gudang lain.");
        }
        if ($to->status !== LocationStatus::ACTIVE) {
            throw new BusinessException("Lokasi {$to->code} berstatus {$to->status->label()}.");
        }
        if (! in_array($to->locationFunction(), self::MOVE_TARGETS, true)) {
            throw new BusinessException("Lokasi {$to->code} bukan lokasi simpan (fungsi {$to->locationFunction()->value}).");
        }
        if ($to->id === $fromId) {
            throw new BusinessException('Lokasi tujuan sama dengan lokasi asal.');
        }
    }

    // ------------------------------------------------------------------ adjustment

    /** Koreksi satu baris stok ke qty hasil hitung. Alasan wajib. */
    public function adjust(int $inventoryId, string $newQty, string $reason, ?string $notes, User $user): array
    {
        $this->assertReason($reason, self::ADJUST_REASONS);
        if (Qty::cmp($newQty, 0) < 0) {
            throw new BusinessException('Qty hasil hitung tidak boleh negatif.');
        }

        return DB::transaction(function () use ($inventoryId, $newQty, $reason, $notes, $user) {
            $row = Inventory::with('item.baseUom', 'location')->lockForUpdate()->find($inventoryId);
            if (! $row) {
                throw new BusinessException('Baris stok tidak ditemukan.');
            }
            if ($row->location->locationFunction() === LocationFunction::STAGING_OUT && Qty::cmp($row->qty_allocated, 0) > 0) {
                throw new BusinessException('Stok staging yang masih teralokasi untuk shipment tidak bisa dikoreksi.');
            }
            $delta = Qty::sub($newQty, $row->qty_on_hand);
            if (Qty::cmp($delta, 0) === 0) {
                throw new BusinessException('Qty hasil hitung sama dengan saldo sistem, tidak ada koreksi.');
            }
            $type = Qty::cmp($delta, 0) > 0 ? MovementType::ADJUST_IN : MovementType::ADJUST_OUT;
            $this->inventory->changeOnHand(StockAttributes::fromInventory($row), $delta, $type, new MovementContext(null, $user->id, null, 1, $reason, $notes), $row->received_at);
            $this->syncLpn($row->lpn_id);

            return ['message' => "{$row->item->sku} di {$row->location->code}: ".Qty::fmt($row->qty_on_hand).' → '.Qty::fmt($newQty).' '.$row->item->baseUom->code.' ('.self::ADJUST_REASONS[$reason].').'];
        });
    }

    /** Tambah stok temuan yang belum ada barisnya (stok lepas, status Tersedia). */
    public function adjustNew(int $locationId, int $itemId, string $qty, ?string $lotNo, ?string $expiry, string $reason, ?string $notes, User $user): array
    {
        $this->assertReason($reason, self::ADJUST_REASONS);
        if (Qty::cmp($qty, 0) <= 0) {
            throw new BusinessException('Qty temuan harus lebih dari 0.');
        }

        return DB::transaction(function () use ($locationId, $itemId, $qty, $lotNo, $expiry, $reason, $notes, $user) {
            $loc = Location::with('locationClass.locationType')->findOrFail($locationId);
            $item = Item::with('baseUom')->findOrFail($itemId);
            if ($loc->status !== LocationStatus::ACTIVE) {
                throw new BusinessException("Lokasi {$loc->code} berstatus {$loc->status->label()}.");
            }
            if ($item->is_lot_tracked && ! $lotNo) {
                throw new BusinessException("Item {$item->sku} wajib nomor lot.");
            }
            if ($item->is_expiry_tracked && ! $expiry) {
                throw new BusinessException("Item {$item->sku} wajib tanggal expired.");
            }
            $this->inventory->changeOnHand(
                new StockAttributes($loc->warehouse_id, $loc->id, $item->id, $item->owner_id, $lotNo ?: null, $expiry ?: null, null, InventoryStatus::AVAILABLE),
                $qty, MovementType::ADJUST_IN, new MovementContext(null, $user->id, null, 1, $reason, $notes),
            );

            return ['message' => "{$item->sku} +".Qty::fmt($qty)." {$item->baseUom->code} ditambahkan di {$loc->code}."];
        });
    }

    /** LPN yang isinya habis -> EMPTY; yang terisi lagi -> ACTIVE. */
    private function syncLpn(?int $lpnId): void
    {
        if (! $lpnId) {
            return;
        }
        $lpn = Lpn::whereKey($lpnId)->lockForUpdate()->first();
        if (! $lpn || ! in_array($lpn->status, [LpnStatus::ACTIVE, LpnStatus::EMPTY], true)) {
            return;
        }
        $has = $this->inventory->lpnHasStock($lpnId);
        if (! $has && $lpn->status === LpnStatus::ACTIVE) {
            $lpn->update(['status' => LpnStatus::EMPTY]);
        } elseif ($has && $lpn->status === LpnStatus::EMPTY) {
            $lpn->update(['status' => LpnStatus::ACTIVE]);
        }
    }

    // ------------------------------------------------------------------ hold / release

    public function hold(int $inventoryId, string $qty, string $reason, ?string $notes, User $user): array
    {
        $this->assertReason($reason, self::HOLD_REASONS);
        if (Qty::cmp($qty, 0) <= 0) {
            throw new BusinessException('Qty hold harus lebih dari 0.');
        }

        return DB::transaction(function () use ($inventoryId, $qty, $reason, $notes, $user) {
            $row = Inventory::with('item.baseUom', 'location')->lockForUpdate()->find($inventoryId);
            if (! $row) {
                throw new BusinessException('Baris stok tidak ditemukan.');
            }
            $this->inventory->change(StockAttributes::fromInventory($row), 0, 0, MovementType::HOLD, new MovementContext(null, $user->id, null, 1, $reason, $notes), null, $qty);

            return ['message' => "{$row->item->sku} di {$row->location->code}: ".Qty::fmt($qty).' '.$row->item->baseUom->code.' di-hold.'];
        });
    }

    public function release(int $inventoryId, string $qty, ?string $notes, User $user): array
    {
        if (Qty::cmp($qty, 0) <= 0) {
            throw new BusinessException('Qty release harus lebih dari 0.');
        }

        return DB::transaction(function () use ($inventoryId, $qty, $notes, $user) {
            $row = Inventory::with('item.baseUom', 'location')->lockForUpdate()->find($inventoryId);
            if (! $row) {
                throw new BusinessException('Baris stok tidak ditemukan.');
            }
            $this->inventory->change(StockAttributes::fromInventory($row), 0, 0, MovementType::RELEASE, new MovementContext(null, $user->id, null, 1, 'RELEASE', $notes), null, Qty::sub(0, $qty));

            return ['message' => "{$row->item->sku} di {$row->location->code}: ".Qty::fmt($qty).' '.$row->item->baseUom->code.' dilepas dari hold.'];
        });
    }

    private function assertReason(string $reason, array $allowed): void
    {
        if (! array_key_exists($reason, $allowed)) {
            throw new BusinessException('Alasan wajib dipilih.');
        }
    }
}
