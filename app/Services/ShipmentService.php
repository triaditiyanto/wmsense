<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Item;
use App\Models\ShipmentDetail;
use App\Models\ShipmentHeader;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Aturan bisnis dokumen shipment (pesanan keluar). Header & detail hanya bisa diubah saat DRAFT.
 * Alokasi / wave ada di WaveService + AllocationService, picking di PickingService, kirim di DispatchService.
 */
class ShipmentService
{
    public function __construct(private DocumentNumberService $numbers) {}

    // ---------------------------------------------------------------- header

    public function createHeader(array $data, User $by): ShipmentHeader
    {
        return DB::transaction(function () use ($data, $by) {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            return ShipmentHeader::create([
                ...$this->headerValues($data),
                'shipment_no' => $this->numbers->next('SHP', $warehouse),
                'status' => ShipmentStatus::DRAFT,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
        });
    }

    public function updateHeader(ShipmentHeader $shipment, array $data, User $by): ShipmentHeader
    {
        return DB::transaction(function () use ($shipment, $data, $by) {
            $header = $this->lockHeader($shipment);
            $this->assertEditable($header);

            $hasDetails = $header->details()->exists();
            if ($hasDetails && (int) $data['owner_id'] !== $header->owner_id) {
                throw new BusinessException('Owner tidak bisa diganti karena dokumen sudah berisi item. Hapus detail dulu.');
            }
            if ($hasDetails && (int) $data['warehouse_id'] !== $header->warehouse_id) {
                throw new BusinessException('Warehouse tidak bisa diganti karena dokumen sudah berisi item.');
            }

            $header->update([...$this->headerValues($data), 'updated_by' => $by->id]);

            return $header;
        });
    }

    /** Validasi customer/alamat milik owner + snapshot alamat kirim. */
    private function headerValues(array $data): array
    {
        $customer = Customer::with('defaultAddress')->findOrFail($data['customer_id']);
        if ($customer->owner_id !== null && $customer->owner_id !== (int) $data['owner_id']) {
            throw new BusinessException("Customer {$customer->name} bukan milik owner dokumen ini.");
        }
        $address = null;
        if (! empty($data['customer_address_id'])) {
            $address = CustomerAddress::where('customer_id', $customer->id)->find($data['customer_address_id']);
            if (! $address) {
                throw new BusinessException('Alamat kirim bukan milik customer yang dipilih.');
            }
        }
        $address ??= $customer->defaultAddress;

        return [
            'warehouse_id' => (int) $data['warehouse_id'],
            'owner_id' => (int) $data['owner_id'],
            'customer_id' => $customer->id,
            'customer_address_id' => $address?->id,
            'ship_to_name' => $customer->name.($address && $address->label ? ' - '.$address->label : ''),
            'ship_to_address' => $address ? $address->fullAddress().($address->pic ? ' (UP: '.$address->pic.($address->phone ? ', '.$address->phone : '').')' : '') : $customer->address,
            'reference_no' => $data['reference_no'] ?? null,
            'order_date' => $data['order_date'] ?? null,
            'expected_ship_date' => $data['expected_ship_date'] ?? null,
            'allocation_rule' => ($data['allocation_rule'] ?? null) ?: null,
            'priority' => (int) ($data['priority'] ?? 5),
            'notes' => $data['notes'] ?? null,
        ];
    }

    public function confirm(ShipmentHeader $shipment, User $by): ShipmentHeader
    {
        return DB::transaction(function () use ($shipment, $by) {
            $header = $this->lockHeader($shipment);
            $this->assertTransition($header, ShipmentStatus::OPEN);
            if (! $header->details()->exists()) {
                throw new BusinessException('Dokumen belum memiliki detail item.');
            }
            $header->update(['status' => ShipmentStatus::OPEN, 'confirmed_by' => $by->id, 'confirmed_at' => now(), 'updated_by' => $by->id]);

            return $header;
        });
    }

    public function cancel(ShipmentHeader $shipment, User $by): ShipmentHeader
    {
        return DB::transaction(function () use ($shipment, $by) {
            $header = $this->lockHeader($shipment);
            $this->assertTransition($header, ShipmentStatus::CANCELLED);
            $header->update(['status' => ShipmentStatus::CANCELLED, 'cancelled_by' => $by->id, 'cancelled_at' => now(), 'updated_by' => $by->id]);

            return $header;
        });
    }

    // ---------------------------------------------------------------- detail

    public function addDetail(ShipmentHeader $shipment, array $data, User $by): ShipmentDetail
    {
        return DB::transaction(function () use ($shipment, $data, $by) {
            $header = $this->lockHeader($shipment);
            $this->assertEditable($header);
            $lineNo = (int) $header->details()->max('line_no') + 1;
            $detail = $header->details()->create([...$this->buildDetailValues($header, $data), 'line_no' => $lineNo]);
            $header->update(['updated_by' => $by->id]);

            return $detail;
        });
    }

    public function updateDetail(ShipmentDetail $detail, array $data, User $by): ShipmentDetail
    {
        return DB::transaction(function () use ($detail, $data, $by) {
            $header = $this->lockHeader($detail->header);
            $this->assertEditable($header);
            $detail->update($this->buildDetailValues($header, $data));
            $header->update(['updated_by' => $by->id]);

            return $detail;
        });
    }

    public function deleteDetail(ShipmentDetail $detail, User $by): void
    {
        DB::transaction(function () use ($detail, $by) {
            $header = $this->lockHeader($detail->header);
            $this->assertEditable($header);
            $detail->delete();
            $header->update(['updated_by' => $by->id]);
        });
    }

    private function buildDetailValues(ShipmentHeader $header, array $data): array
    {
        $item = Item::with(['baseUom', 'uoms'])->findOrFail($data['item_id']);
        if ($item->owner_id !== $header->owner_id) {
            throw new BusinessException("Item {$item->sku} bukan milik owner dokumen ini.");
        }
        if (! $item->is_active) {
            throw new BusinessException("Item {$item->sku} tidak aktif.");
        }
        $factor = $item->conversionFactor((int) $data['uom_id']);
        if ($factor === null) {
            throw new BusinessException("UOM yang dipilih tidak terdaftar untuk item {$item->sku}.");
        }
        $qtyBase = Qty::mul($data['qty_ordered'], $factor);
        if (! $item->baseUom->allow_decimal && ! Qty::isWhole($qtyBase)) {
            throw new BusinessException("Qty dalam base UOM ({$item->baseUom->code}) harus bilangan bulat, hasil konversi: ".Qty::fmt($qtyBase));
        }

        return [
            'item_id' => $item->id,
            'uom_id' => (int) $data['uom_id'],
            'qty_ordered' => $data['qty_ordered'],
            'conversion_factor' => $factor,
            'qty_ordered_base' => $qtyBase,
            'lot_no' => ($data['lot_no'] ?? null) ?: null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    // ---------------------------------------------------------------- helper

    private function lockHeader(ShipmentHeader $shipment): ShipmentHeader
    {
        return ShipmentHeader::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
    }

    private function assertEditable(ShipmentHeader $header): void
    {
        if (! $header->status->isEditable()) {
            throw new BusinessException("Dokumen berstatus {$header->status->label()} tidak bisa diubah.");
        }
    }

    private function assertTransition(ShipmentHeader $header, ShipmentStatus $to): void
    {
        if (! $header->status->canTransitionTo($to)) {
            throw new BusinessException("Status {$header->status->label()} tidak bisa diubah ke {$to->label()}.");
        }
    }
}
