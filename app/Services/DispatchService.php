<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\MovementType;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\DeliveryOrderHeader;
use App\Models\Inventory;
use App\Models\ShipmentDetail;
use App\Models\ShipmentHeader;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dispatch: shipment yang sudah PICKED (barang di staging) disusun ke DO / Surat Jalan, lalu dikirim.
 *   createDo()  : DO DRAFT berisi shipment PICKED satu gudang (bisa beberapa customer = multi-drop).
 *   dispatch()  : stok staging keluar gudang (SHIP: on_hand & allocated turun), shipment DISPATCHED, DO DISPATCHED.
 *   cancel()    : DO DRAFT dibatalkan, shipment kembali ke daftar siap kirim.
 */
class DispatchService
{
    public function __construct(private InventoryService $inventory, private DocumentNumberService $numbers) {}

    public function createDo(array $data, array $shipmentIds, User $user): DeliveryOrderHeader
    {
        return DB::transaction(function () use ($data, $shipmentIds, $user) {
            // DO boleh dibuat kosong (manual) lalu diisi shipment belakangan; bila kosong, gudang wajib dipilih
            $shipments = $shipmentIds ? $this->lockReadyShipments($shipmentIds) : collect();
            $warehouseId = (int) ($data['warehouse_id'] ?? $shipments->first()?->warehouse_id);
            if (! $warehouseId) {
                throw new BusinessException('Pilih gudang untuk Surat Jalan kosong.');
            }
            if ($shipments->contains(fn ($s) => $s->warehouse_id !== $warehouseId)) {
                throw new BusinessException('Semua shipment dalam satu DO harus dari gudang yang sama.');
            }
            $do = DeliveryOrderHeader::create([
                ...$this->headerValues($data),
                'do_no' => $this->numbers->next('DO', Warehouse::findOrFail($warehouseId)),
                'warehouse_id' => $warehouseId,
                'status' => DeliveryOrderStatus::DRAFT,
                'created_by' => $user->id,
            ]);
            $shipments->each(fn ($s) => $s->update(['delivery_order_id' => $do->id, 'updated_by' => $user->id]));

            return $do;
        });
    }

    public function updateDo(DeliveryOrderHeader $do, array $data): DeliveryOrderHeader
    {
        return DB::transaction(function () use ($do, $data) {
            $do = $this->lockDraft($do);
            $do->update($this->headerValues($data));

            return $do;
        });
    }

    public function addShipments(DeliveryOrderHeader $do, array $shipmentIds, User $user): void
    {
        DB::transaction(function () use ($do, $shipmentIds, $user) {
            $do = $this->lockDraft($do);
            $shipments = $this->lockReadyShipments($shipmentIds);
            if ($shipments->contains(fn ($s) => $s->warehouse_id !== $do->warehouse_id)) {
                throw new BusinessException('Shipment harus dari gudang yang sama dengan DO.');
            }
            $shipments->each(fn ($s) => $s->update(['delivery_order_id' => $do->id, 'updated_by' => $user->id]));
        });
    }

    public function removeShipment(DeliveryOrderHeader $do, ShipmentHeader $shipment, User $user): void
    {
        DB::transaction(function () use ($do, $shipment, $user) {
            $do = $this->lockDraft($do);
            $shipment = ShipmentHeader::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($shipment->delivery_order_id !== $do->id) {
                throw new BusinessException("Shipment {$shipment->shipment_no} tidak ada di DO ini.");
            }
            $shipment->update(['delivery_order_id' => null, 'updated_by' => $user->id]);
        });
    }

    /** Kirim: semua stok hasil pick (di staging) keluar gudang. */
    public function dispatch(DeliveryOrderHeader $do, User $user): DeliveryOrderHeader
    {
        return DB::transaction(function () use ($do, $user) {
            $do = $this->lockDraft($do);
            $shipments = $do->shipments()->lockForUpdate()->get();
            if ($shipments->isEmpty()) {
                throw new BusinessException('DO belum berisi shipment.');
            }
            foreach ($shipments as $shipment) {
                if ($shipment->status !== ShipmentStatus::PICKED) {
                    throw new BusinessException("Shipment {$shipment->shipment_no} berstatus {$shipment->status->label()}, belum siap kirim.");
                }
                $lines = $shipment->allocations()->whereIn('status', [AllocationStatus::PICKED, AllocationStatus::SHORT])
                    ->where('qty_picked_base', '>', 0)->orderBy('id')->get();
                $seq = 0;
                foreach ($lines as $line) {
                    $staged = Inventory::whereKey($line->inventory_id)->firstOrFail(); // atribut stok (lot/exp/status) sumber
                    $this->inventory->change(
                        new StockAttributes($shipment->warehouse_id, $line->staging_location_id, $line->item_id, $line->owner_id,
                            $line->lot_no, $line->expiry_date?->format('Y-m-d'), null, $staged->status),
                        Qty::sub(0, $line->qty_picked_base), Qty::sub(0, $line->qty_picked_base), MovementType::SHIP,
                        new MovementContext($do, $user->id, null, ++$seq, 'SHIP', "{$shipment->shipment_no} · {$do->do_no}"),
                    );
                    $detail = ShipmentDetail::whereKey($line->shipment_detail_id)->lockForUpdate()->firstOrFail();
                    $detail->update(['qty_shipped_base' => Qty::add($detail->qty_shipped_base, $line->qty_picked_base)]);
                }
                $shipment->update(['status' => ShipmentStatus::DISPATCHED, 'updated_by' => $user->id]);
            }
            $do->update(['status' => DeliveryOrderStatus::DISPATCHED, 'dispatched_by' => $user->id, 'dispatched_at' => now()]);

            return $do;
        });
    }

    public function cancel(DeliveryOrderHeader $do, User $user): void
    {
        DB::transaction(function () use ($do, $user) {
            $do = $this->lockDraft($do);
            $do->shipments()->lockForUpdate()->get()->each(fn ($s) => $s->update(['delivery_order_id' => null, 'updated_by' => $user->id]));
            $do->update(['status' => DeliveryOrderStatus::CANCELLED, 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
        });
    }

    public function markPrinted(DeliveryOrderHeader $do): void
    {
        $do->increment('print_count', 1, ['last_printed_at' => now()]);
    }

    // ---------------------------------------------------------------- helper

    private function headerValues(array $data): array
    {
        return [
            'ship_date' => $data['ship_date'] ?? null,
            'vehicle_no' => ($data['vehicle_no'] ?? null) ? strtoupper(trim($data['vehicle_no'])) : null,
            'driver_name' => $data['driver_name'] ?? null,
            'driver_phone' => $data['driver_phone'] ?? null,
            'carrier' => $data['carrier'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function lockDraft(DeliveryOrderHeader $do): DeliveryOrderHeader
    {
        $do = DeliveryOrderHeader::whereKey($do->id)->lockForUpdate()->firstOrFail();
        if ($do->status !== DeliveryOrderStatus::DRAFT) {
            throw new BusinessException("DO {$do->do_no} sudah {$do->status->label()}, tidak bisa diubah.");
        }

        return $do;
    }

    /** @return Collection<int, ShipmentHeader> shipment PICKED yang belum masuk DO */
    private function lockReadyShipments(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            throw new BusinessException('Pilih minimal satu shipment.');
        }
        $shipments = ShipmentHeader::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($shipments->count() !== count($ids)) {
            throw new BusinessException('Ada shipment yang tidak ditemukan.');
        }
        foreach ($shipments as $s) {
            if ($s->status !== ShipmentStatus::PICKED) {
                throw new BusinessException("Shipment {$s->shipment_no} berstatus {$s->status->label()} (harus Siap Kirim).");
            }
            if ($s->delivery_order_id) {
                throw new BusinessException("Shipment {$s->shipment_no} sudah masuk DO lain.");
            }
        }

        return $shipments;
    }
}
