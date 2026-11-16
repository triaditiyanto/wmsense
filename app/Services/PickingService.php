<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\LpnStatus;
use App\Enums\MovementType;
use App\Enums\ShipmentStatus;
use App\Enums\WaveStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmationRequiredException;
use App\Models\Allocation;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Lpn;
use App\Models\ShipmentDetail;
use App\Models\ShipmentHeader;
use App\Models\User;
use App\Models\WaveHeader;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Picking per wave. Baris pick = o_allocation (urut pick_sequence lokasi).
 *   start()  : wave ALLOCATED -> PICKING, tentukan lokasi staging outbound (fungsi STAGING_OUT).
 *   pick()   : satu baris: stok keluar dari lokasi sumber (PICK_OUT: on_hand & allocated turun) dan masuk ke staging
 *              (PICK_IN: on_hand & allocated naik, tetap tereservasi sampai dikirim). Qty kurang = short pick: sisa
 *              alokasi dilepas (DEALLOCATE), butuh konfirmasi (OUT_PICKING:approve).
 *   finish() : tutup baris yang belum dipick sebagai short (butuh approve) -> wave PICKED, shipment PICKED
 *              (shipment tanpa hasil pick kembali OPEN agar bisa di-wave lagi).
 * Desktop & mobile memakai service ini lewat PickingController (aturan bisnis identik).
 */
class PickingService
{
    public function __construct(private InventoryService $inventory, private AllocationService $allocation) {}

    /** Wave yang siap / sedang dipick. */
    public function queue(?int $warehouseId = null, ?string $q = null): Collection
    {
        return WaveHeader::query()
            ->whereIn('status', [WaveStatus::ALLOCATED, WaveStatus::PICKING])
            ->when($warehouseId, fn ($w) => $w->where('warehouse_id', $warehouseId))
            ->when($q, fn ($w) => $w->where(fn ($s) => $s->where('wave_no', 'like', "%{$q}%")
                ->orWhereHas('shipments', fn ($sh) => $sh->where('shipment_no', 'like', "%{$q}%")->orWhere('ship_to_name', 'like', "%{$q}%"))))
            ->with(['shipments.customer', 'stagingLocation'])
            ->withCount(['shipments', 'allocations as open_count' => fn ($a) => $a->where('status', AllocationStatus::OPEN),
                'allocations as done_count' => fn ($a) => $a->whereIn('status', [AllocationStatus::PICKED, AllocationStatus::SHORT])])
            ->orderByRaw("FIELD(status, 'PICKING', 'ALLOCATED')")->orderBy('id')
            ->get();
    }

    /** Data wave untuk layar desktop/mobile: baris pick urut rute + progres + pilihan staging. */
    public function payload(WaveHeader $wave): array
    {
        $wave->loadMissing(['shipments.customer', 'stagingLocation']);
        $lines = $wave->allocations()->with(['location', 'item.baseUom', 'item.uoms', 'lpn', 'shipment', 'stagingLocation', 'picker'])
            ->where('status', '!=', AllocationStatus::CANCELLED)
            ->get()
            ->sortBy(fn (Allocation $a) => [$a->status === AllocationStatus::OPEN ? 0 : 1, $a->location->pick_sequence ?? PHP_INT_MAX, $a->location->code, $a->id])
            ->values();
        $open = $lines->where('status', AllocationStatus::OPEN)->count();

        return [
            'id' => $wave->id,
            'wave_no' => $wave->wave_no,
            'type' => $wave->typeLabel(),
            'status' => $wave->status->value,
            'status_label' => $wave->status->label(),
            'rule' => $wave->allocation_rule?->value,
            'shipments' => $wave->shipments->map(fn ($s) => ['no' => $s->shipment_no, 'customer' => $s->ship_to_name ?? $s->customer->name, 'status' => $s->status->label()])->values(),
            'staging' => $wave->stagingLocation ? ['id' => $wave->stagingLocation->id, 'code' => $wave->stagingLocation->code] : null,
            'stagings' => $this->stagingLocations($wave->warehouse_id)->map(fn ($l) => ['id' => $l->id, 'code' => $l->code])->values(),
            'progress' => ['total' => $lines->count(), 'open' => $open, 'done' => $lines->count() - $open,
                'short' => $lines->where('status', AllocationStatus::SHORT)->count()],
            'lines' => $lines->map(fn (Allocation $a, int $i) => [
                'id' => $a->id, 'seq' => $i + 1,
                'shipment_no' => $a->shipment->shipment_no,
                'location_id' => $a->location_id, 'location' => $a->location->code,
                'sku' => $a->item->sku, 'name' => $a->item->name,
                'barcodes' => array_values(array_filter(array_unique([$a->item->barcode, $a->item->sku, ...$a->item->uoms->pluck('barcode')->all()]))),
                'lot_no' => $a->lot_no, 'expiry_date' => $a->expiry_date?->format('d/m/Y'),
                'lpn_no' => $a->lpn?->lpn_no,
                'qty' => Qty::fmt($a->qty_allocated_base), 'qty_raw' => rtrim(rtrim($a->qty_allocated_base, '0'), '.'),
                'qty_picked' => Qty::fmt($a->qty_picked_base), 'uom' => $a->item->baseUom->code,
                'status' => $a->status->value, 'status_label' => $a->status->label(), 'color' => $a->status->color(),
                'staging' => $a->stagingLocation?->code, 'picked_by' => $a->picker?->name, 'picked_at' => $a->picked_at?->format('d/m H:i'),
                'short_reason' => $a->short_reason,
            ])->values(),
        ];
    }

    /** Mulai picking: ALLOCATED -> PICKING + staging tujuan. Boleh dipanggil ulang untuk ganti staging. */
    public function start(WaveHeader $wave, User $user, ?int $stagingLocationId = null, string $source = 'DESKTOP'): WaveHeader
    {
        return DB::transaction(function () use ($wave, $user, $stagingLocationId) {
            $wave = WaveHeader::whereKey($wave->id)->lockForUpdate()->firstOrFail();
            if (! $wave->status->canPick()) {
                throw new BusinessException("Wave {$wave->wave_no} berstatus {$wave->status->label()}, tidak bisa dipick.");
            }
            $staging = $this->resolveStaging($wave, $stagingLocationId);
            $wave->update([
                'status' => WaveStatus::PICKING, 'staging_location_id' => $staging->id,
                'picking_started_at' => $wave->picking_started_at ?? now(),
            ]);
            $wave->shipments()->where('status', ShipmentStatus::ALLOCATED)->lockForUpdate()->get()
                ->each(fn ($s) => $s->update(['status' => ShipmentStatus::PICKING, 'updated_by' => $user->id]));

            return $wave;
        });
    }

    /**
     * Pick satu baris. $qty < alokasi = short pick (sisa dilepas), $qty = 0 = tidak ketemu.
     * $confirm = setujui short pick (butuh OUT_PICKING:approve).
     */
    public function pick(Allocation $line, string|int|float $qty, User $user, string $source, ?string $idempotencyKey = null,
        ?int $stagingLocationId = null, bool $confirm = false, ?string $reason = null): Allocation
    {
        return DB::transaction(function () use ($line, $qty, $user, $source, $idempotencyKey, $stagingLocationId, $confirm, $reason) {
            if ($idempotencyKey && InventoryMovement::where('idempotency_key', $idempotencyKey)->exists()) {
                return Allocation::findOrFail($line->id); // request ulang (jaringan putus) -> sudah diproses
            }
            $wave = WaveHeader::whereKey($line->wave_id)->lockForUpdate()->firstOrFail();
            if (! $wave->status->canPick()) {
                throw new BusinessException("Wave {$wave->wave_no} berstatus {$wave->status->label()}.");
            }
            if ($wave->status === WaveStatus::ALLOCATED || $stagingLocationId) {
                $wave = $this->start($wave, $user, $stagingLocationId ?? $wave->staging_location_id, $source);
            }
            $line = Allocation::whereKey($line->id)->lockForUpdate()->firstOrFail();
            if ($line->status !== AllocationStatus::OPEN) {
                throw new BusinessException("Baris ini sudah {$line->status->label()}.");
            }
            $qty = (string) $qty;
            if (Qty::cmp($qty, 0) < 0 || Qty::cmp($qty, $line->qty_allocated_base) > 0) {
                throw new BusinessException('Qty pick harus antara 0 dan '.Qty::fmt($line->qty_allocated_base).'.');
            }
            $short = Qty::sub($line->qty_allocated_base, $qty);
            if (Qty::cmp($short, 0) > 0) {
                $canConfirm = $user->hasAccess('OUT_PICKING', 'approve');
                $warn = ['Qty dipick '.Qty::fmt($qty).' dari '.Qty::fmt($line->qty_allocated_base).'. Sisa '.Qty::fmt($short).' akan dilepas (short pick).'];
                if (! $confirm) {
                    throw new ConfirmationRequiredException($warn, $canConfirm);
                }
                if (! $canConfirm) {
                    throw new BusinessException('Short pick butuh persetujuan SPV. '.$warn[0]);
                }
            }

            $shipment = ShipmentHeader::whereKey($line->shipment_header_id)->lockForUpdate()->firstOrFail();
            $inv = Inventory::whereKey($line->inventory_id)->lockForUpdate()->firstOrFail();
            $note = "{$shipment->shipment_no} · {$wave->wave_no}";
            $seq = 0;
            if (Qty::cmp($qty, 0) > 0) {
                $this->inventory->change(StockAttributes::fromInventory($inv), Qty::sub(0, $qty), Qty::sub(0, $qty), MovementType::PICK_OUT,
                    new MovementContext($wave, $user->id, $idempotencyKey, ++$seq, 'PICK', $note));
                $this->inventory->change(
                    new StockAttributes($inv->warehouse_id, $wave->staging_location_id, $inv->item_id, $inv->owner_id, $inv->lot_no,
                        $inv->expiry_date?->format('Y-m-d'), null, $inv->status),
                    $qty, $qty, MovementType::PICK_IN, new MovementContext($wave, $user->id, $idempotencyKey, ++$seq, 'PICK', $note), $inv->received_at,
                );
                if ($inv->lpn_id && ! $this->inventory->lpnHasStock($inv->lpn_id)) {
                    Lpn::whereKey($inv->lpn_id)->update(['status' => LpnStatus::EMPTY]);
                }
            }
            if (Qty::cmp($short, 0) > 0) {
                $this->inventory->change(StockAttributes::fromInventory($inv), 0, Qty::sub(0, $short), MovementType::DEALLOCATE,
                    new MovementContext($wave, $user->id, $idempotencyKey, ++$seq, 'SHORT_PICK', $note));
            }

            $line->update([
                'qty_picked_base' => $qty, 'status' => Qty::cmp($short, 0) > 0 ? AllocationStatus::SHORT : AllocationStatus::PICKED,
                'staging_location_id' => $wave->staging_location_id, 'picked_by' => $user->id, 'picked_at' => now(),
                'pick_source' => $source, 'short_reason' => Qty::cmp($short, 0) > 0 ? ($reason ?: 'Short pick') : null,
            ]);
            $detail = ShipmentDetail::whereKey($line->shipment_detail_id)->lockForUpdate()->firstOrFail();
            $detail->update([
                'qty_picked_base' => Qty::add($detail->qty_picked_base, $qty),
                'qty_allocated_base' => Qty::sub($detail->qty_allocated_base, $short),
            ]);
            if ($shipment->status === ShipmentStatus::ALLOCATED) {
                $shipment->update(['status' => ShipmentStatus::PICKING, 'updated_by' => $user->id]);
            }
            $this->closeIfDone($wave, $user);

            return $line;
        });
    }

    /** Selesai picking: baris yang belum dipick ditutup sebagai short (butuh approve bila ada). */
    public function finish(WaveHeader $wave, User $user, bool $confirm = false): WaveHeader
    {
        return DB::transaction(function () use ($wave, $user, $confirm) {
            $wave = WaveHeader::whereKey($wave->id)->lockForUpdate()->firstOrFail();
            if (! $wave->status->canPick()) {
                throw new BusinessException("Wave {$wave->wave_no} berstatus {$wave->status->label()}.");
            }
            $open = $wave->allocations()->where('status', AllocationStatus::OPEN)->lockForUpdate()->get();
            if ($open->isNotEmpty()) {
                $canConfirm = $user->hasAccess('OUT_PICKING', 'approve');
                $warn = ["{$open->count()} baris belum dipick akan ditutup sebagai kurang (short) dan alokasinya dilepas."];
                if (! $confirm) {
                    throw new ConfirmationRequiredException($warn, $canConfirm);
                }
                if (! $canConfirm) {
                    throw new BusinessException('Menutup baris yang belum dipick butuh persetujuan SPV.');
                }
                $this->allocation->release($open, $user, 'Ditutup saat selesai picking', AllocationStatus::SHORT);
            }
            $this->closeIfDone($wave, $user, force: true);

            return $wave->fresh();
        });
    }

    /** Bila semua baris tertutup: wave PICKED; shipment -> PICKED (ada hasil) atau OPEN (tidak ada yang terpick). */
    private function closeIfDone(WaveHeader $wave, User $user, bool $force = false): void
    {
        if (! $force && $wave->allocations()->where('status', AllocationStatus::OPEN)->exists()) {
            return;
        }
        foreach ($wave->shipments()->lockForUpdate()->get() as $s) {
            if (! in_array($s->status, [ShipmentStatus::WAVED, ShipmentStatus::ALLOCATED, ShipmentStatus::PICKING], true)) {
                continue;
            }
            $picked = (string) $s->details()->sum('qty_picked_base');
            $s->update(Qty::cmp($picked, 0) > 0
                ? ['status' => ShipmentStatus::PICKED, 'updated_by' => $user->id]
                : ['status' => ShipmentStatus::OPEN, 'wave_id' => null, 'updated_by' => $user->id]);
        }
        $wave->update(['status' => WaveStatus::PICKED, 'picked_at' => now()]);
    }

    /** @return Collection<int, Location> lokasi staging outbound aktif di gudang */
    public function stagingLocations(int $warehouseId): Collection
    {
        return Location::where('warehouse_id', $warehouseId)->where('status', LocationStatus::ACTIVE)
            ->whereHas('locationClass.locationType', fn ($t) => $t->where('location_function', LocationFunction::STAGING_OUT))
            ->orderBy('code')->get();
    }

    private function resolveStaging(WaveHeader $wave, ?int $id): Location
    {
        $list = $this->stagingLocations($wave->warehouse_id);
        if ($list->isEmpty()) {
            throw new BusinessException('Belum ada lokasi staging outbound (tipe lokasi fungsi STAGING_OUT) di gudang ini.');
        }
        $loc = $id ? $list->firstWhere('id', $id) : ($wave->staging_location_id ? $list->firstWhere('id', $wave->staging_location_id) : null);
        if ($id && ! $loc) {
            throw new BusinessException('Lokasi staging tidak valid (harus lokasi aktif berfungsi STAGING_OUT di gudang ini).');
        }

        return $loc ?? $list->first();
    }
}
