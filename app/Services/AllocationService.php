<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\InventoryStatus;
use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\MovementType;
use App\Enums\RotationRule;
use App\Enums\ShipmentStatus;
use App\Models\Allocation;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\ShipmentDetail;
use App\Models\ShipmentHeader;
use App\Models\User;
use App\Models\WaveHeader;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use Illuminate\Support\Collection;

/**
 * Algoritma alokasi stok untuk wave (FIFO / FEFO / LIFO / LEFO).
 *
 * Aturan yang dipakai per baris: wave.allocation_rule > shipment.allocation_rule > item.rotation_rule.
 * Kandidat stok: status AVAILABLE, qty_available > 0, lokasi ACTIVE berfungsi STORAGE / PICK_FACE, item & owner sama,
 * belum kedaluwarsa, dan lot sesuai bila baris shipment membatasi lot. Kandidat diurutkan menurut aturan,
 * lalu diambil berurutan (boleh pecah ke beberapa lokasi) sampai qty pesanan terpenuhi. Sisa yang tidak
 * terpenuhi dilaporkan sebagai "short" (bisa dialokasi ulang setelah ada stok baru).
 *
 * Setiap alokasi menaikkan o_inventory.qty_allocated (movement ALLOCATE) dan membuat baris o_allocation
 * (= baris pick list). Harus dipanggil di dalam transaksi yang sudah mengunci wave (lihat WaveService).
 */
class AllocationService
{
    public function __construct(private InventoryService $inventory) {}

    /** @return array{lines:int, qty:string, short:list<array>} */
    public function allocateWave(WaveHeader $wave, User $user): array
    {
        $summary = ['lines' => 0, 'qty' => '0', 'short' => []];
        $shipments = $wave->shipments()->with('details.item')->lockForUpdate()->get();
        $allowedLocations = $this->allocatableLocationIds($wave->warehouse_id);

        foreach ($shipments as $shipment) {
            if (! in_array($shipment->status, [ShipmentStatus::WAVED, ShipmentStatus::ALLOCATED, ShipmentStatus::PICKING], true)) {
                continue;
            }
            $seq = 0;
            foreach ($shipment->details as $detail) {
                $remaining = $detail->qtyOpenBase();
                if (Qty::cmp($remaining, 0) <= 0) {
                    continue;
                }
                $rule = $shipment->ruleFor($detail->item, $wave);
                $candidates = $this->sort($this->candidates($shipment, $detail, $allowedLocations), $rule);

                foreach ($candidates as $inv) {
                    if (Qty::cmp($remaining, 0) <= 0) {
                        break;
                    }
                    $take = Qty::cmp($remaining, $inv->qty_available) < 0 ? $remaining : $inv->qty_available;
                    $this->inventory->change(StockAttributes::fromInventory($inv), 0, $take, MovementType::ALLOCATE,
                        new MovementContext($shipment, $user->id, null, ++$seq, 'ALLOCATE', "Wave {$wave->wave_no} · {$shipment->shipment_no} #{$detail->line_no}"));
                    Allocation::create([
                        'wave_id' => $wave->id, 'shipment_header_id' => $shipment->id, 'shipment_detail_id' => $detail->id,
                        'inventory_id' => $inv->id, 'location_id' => $inv->location_id, 'lpn_id' => $inv->lpn_id,
                        'item_id' => $inv->item_id, 'owner_id' => $inv->owner_id, 'lot_no' => $inv->lot_no, 'expiry_date' => $inv->expiry_date,
                        'qty_allocated_base' => $take, 'status' => AllocationStatus::OPEN, 'rule' => $rule,
                    ]);
                    $remaining = Qty::sub($remaining, $take);
                    $detail->qty_allocated_base = Qty::add($detail->qty_allocated_base, $take);
                    $summary['lines']++;
                    $summary['qty'] = Qty::add($summary['qty'], $take);
                }
                $detail->save();
                if (Qty::cmp($remaining, 0) > 0) {
                    $summary['short'][] = ['shipment_no' => $shipment->shipment_no, 'line_no' => $detail->line_no, 'sku' => $detail->item->sku, 'qty' => $remaining];
                }
            }

            $allocated = $shipment->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_allocated_base), '0');
            if ($shipment->status === ShipmentStatus::WAVED && Qty::cmp($allocated, 0) > 0) {
                $shipment->update(['status' => ShipmentStatus::ALLOCATED, 'updated_by' => $user->id]);
            }
        }

        return $summary;
    }

    /**
     * Lepas alokasi yang belum dipick (DEALLOCATE). qty_allocated_base di baris shipment ikut turun.
     *
     * @param  Collection<int, Allocation>  $allocations  baris OPEN (sudah dikunci pemanggil)
     */
    public function release(Collection $allocations, User $user, string $reason, AllocationStatus $to = AllocationStatus::CANCELLED): void
    {
        $seq = 0;
        $allocations->loadMissing('shipment');
        foreach ($allocations->sortBy('inventory_id') as $a) {
            if ($a->status !== AllocationStatus::OPEN) {
                continue;
            }
            $inv = Inventory::whereKey($a->inventory_id)->lockForUpdate()->firstOrFail();
            $this->inventory->change(StockAttributes::fromInventory($inv), 0, Qty::sub(0, $a->qty_allocated_base), MovementType::DEALLOCATE,
                new MovementContext($a->shipment, $user->id, null, ++$seq, $reason));
            $a->update(['status' => $to, 'short_reason' => $to === AllocationStatus::SHORT ? $reason : null]);
            $detail = ShipmentDetail::whereKey($a->shipment_detail_id)->lockForUpdate()->firstOrFail();
            $detail->update(['qty_allocated_base' => Qty::sub($detail->qty_allocated_base, $a->qty_allocated_base)]);
        }
    }

    /** Pratinjau urutan kandidat untuk satu item (tanpa mengunci / mengubah apa pun). Dipakai layar simulasi. */
    public function preview(int $warehouseId, int $itemId, int $ownerId, RotationRule $rule, ?string $lotNo = null): Collection
    {
        $q = $this->candidateQuery($warehouseId, $itemId, $ownerId, $lotNo, $this->allocatableLocationIds($warehouseId));

        return $this->sort($q->get(), $rule);
    }

    // ---------------------------------------------------------------- internal

    /** @return list<int> */
    private function allocatableLocationIds(int $warehouseId): array
    {
        return Location::where('warehouse_id', $warehouseId)->where('status', LocationStatus::ACTIVE)
            ->whereHas('locationClass.locationType', fn ($t) => $t->whereIn('location_function', [LocationFunction::STORAGE, LocationFunction::PICK_FACE]))
            ->pluck('id')->all();
    }

    /** @return Collection<int, Inventory> */
    private function candidates(ShipmentHeader $shipment, ShipmentDetail $detail, array $locationIds): Collection
    {
        return $this->candidateQuery($shipment->warehouse_id, $detail->item_id, $shipment->owner_id, $detail->lot_no, $locationIds)
            ->lockForUpdate()->get();
    }

    private function candidateQuery(int $warehouseId, int $itemId, int $ownerId, ?string $lotNo, array $locationIds)
    {
        return Inventory::query()->with(['location', 'lpn'])
            ->where('warehouse_id', $warehouseId)->where('item_id', $itemId)->where('owner_id', $ownerId)
            ->where('status', InventoryStatus::AVAILABLE)->where('qty_available', '>', 0)
            ->whereIn('location_id', $locationIds)
            ->when($lotNo, fn ($q) => $q->where('lot_no', $lotNo))
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->orderBy('id');
    }

    /** Urutkan kandidat menurut aturan; seri dipecah oleh pick_sequence lokasi lalu id. */
    public function sort(Collection $rows, RotationRule $rule): Collection
    {
        $ts = fn ($d) => $d?->getTimestamp() ?? 0;
        $seq = fn (Inventory $i) => $i->location?->pick_sequence ?? PHP_INT_MAX;
        $key = match ($rule) {
            RotationRule::FIFO => fn (Inventory $i) => [0, $ts($i->received_at), $seq($i), $i->id],
            RotationRule::LIFO => fn (Inventory $i) => [0, -$ts($i->received_at), $seq($i), $i->id],
            RotationRule::FEFO => fn (Inventory $i) => [$i->expiry_date ? 0 : 1, $ts($i->expiry_date), $ts($i->received_at), $seq($i), $i->id],
            RotationRule::LEFO => fn (Inventory $i) => [$i->expiry_date ? 0 : 1, -$ts($i->expiry_date), -$ts($i->received_at), $seq($i), $i->id],
        };

        return $rows->sort(fn ($a, $b) => $key($a) <=> $key($b))->values();
    }
}
