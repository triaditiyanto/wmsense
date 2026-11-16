<?php

namespace App\Services;

use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessException;
use App\Models\Item;
use App\Models\ReceiptDetail;
use App\Models\ReceiptHeader;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Semua aturan bisnis dokumen receipt ada di sini, bukan di controller.
 * Controller cukup: validasi input -> panggil service -> kirim response.
 */
class ReceiptService
{
    public function __construct(private DocumentNumberService $numbers)
    {
        // Laravel otomatis meng-inject DocumentNumberService (service container)
    }

    // ---------------------------------------------------------------- header

    public function createHeader(array $data, User $by): ReceiptHeader
    {
        return DB::transaction(function () use ($data, $by) {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            return ReceiptHeader::create([
                ...$data,
                'receipt_no' => $this->numbers->next('RCV', $warehouse),
                'status' => ReceiptStatus::DRAFT,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
        });
    }

    public function updateHeader(ReceiptHeader $receipt, array $data, User $by): ReceiptHeader
    {
        return DB::transaction(function () use ($receipt, $data, $by) {
            $header = $this->lockHeader($receipt);
            $this->assertEditable($header);

            $hasDetails = $header->details()->exists();
            if ($hasDetails && (int) $data['owner_id'] !== $header->owner_id) {
                throw new BusinessException('Owner tidak bisa diganti karena dokumen sudah berisi item. Hapus detail dulu.');
            }
            if ($hasDetails && (int) $data['warehouse_id'] !== $header->warehouse_id) {
                throw new BusinessException('Warehouse tidak bisa diganti karena dokumen sudah berisi item.');
            }

            $header->update([...$data, 'updated_by' => $by->id]);

            return $header;
        });
    }

    public function confirm(ReceiptHeader $receipt, User $by): ReceiptHeader
    {
        return DB::transaction(function () use ($receipt, $by) {
            $header = $this->lockHeader($receipt);
            $this->assertTransition($header, ReceiptStatus::OPEN);

            if (! $header->details()->exists()) {
                throw new BusinessException('Dokumen belum memiliki detail item.');
            }

            $header->update([
                'status' => ReceiptStatus::OPEN,
                'confirmed_by' => $by->id,
                'confirmed_at' => now(),
                'updated_by' => $by->id,
            ]);

            return $header;
        });
    }

    public function cancel(ReceiptHeader $receipt, User $by): ReceiptHeader
    {
        return DB::transaction(function () use ($receipt, $by) {
            $header = $this->lockHeader($receipt);
            $this->assertTransition($header, ReceiptStatus::CANCELLED);

            if ($header->details()->where('qty_received_base', '>', 0)->exists()) {
                throw new BusinessException('Dokumen tidak bisa dibatalkan karena sudah ada barang diterima.');
            }

            $header->update([
                'status' => ReceiptStatus::CANCELLED,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            return $header;
        });
    }

    // ---------------------------------------------------------------- detail

    public function addDetail(ReceiptHeader $receipt, array $data, User $by): ReceiptDetail
    {
        return DB::transaction(function () use ($receipt, $data, $by) {
            $header = $this->lockHeader($receipt); // kunci header -> line_no aman dari race
            $this->assertEditable($header);

            $lineNo = (int) $header->details()->max('line_no') + 1;
            $detail = $header->details()->create([
                ...$this->buildDetailValues($header, $data),
                'line_no' => $lineNo,
            ]);
            $header->update(['updated_by' => $by->id]);

            return $detail;
        });
    }

    public function updateDetail(ReceiptDetail $detail, array $data, User $by): ReceiptDetail
    {
        return DB::transaction(function () use ($detail, $data, $by) {
            $header = $this->lockHeader($detail->header);
            $this->assertEditable($header);

            $detail->update($this->buildDetailValues($header, $data));
            $header->update(['updated_by' => $by->id]);

            return $detail;
        });
    }

    public function deleteDetail(ReceiptDetail $detail, User $by): void
    {
        DB::transaction(function () use ($detail, $by) {
            $header = $this->lockHeader($detail->header);
            $this->assertEditable($header);

            $detail->delete();
            $header->update(['updated_by' => $by->id]);
        });
    }

    /** Validasi bisnis + konversi UOM -> base UOM. */
    private function buildDetailValues(ReceiptHeader $header, array $data): array
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

        $qtyBase = Qty::mul($data['qty_expected'], $factor);
        if (! $item->baseUom->allow_decimal && ! Qty::isWhole($qtyBase)) {
            throw new BusinessException(
                "Qty dalam base UOM ({$item->baseUom->code}) harus bilangan bulat, hasil konversi: ".Qty::fmt($qtyBase)
            );
        }

        return [
            'item_id' => $item->id,
            'uom_id' => (int) $data['uom_id'],
            'qty_expected' => $data['qty_expected'],
            'conversion_factor' => $factor,
            'qty_expected_base' => $qtyBase,
            'lot_no' => $data['lot_no'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    // ---------------------------------------------------------------- helper

    /** SELECT ... FOR UPDATE ulang di dalam transaksi, supaya status yang dicek adalah yang terbaru. */
    private function lockHeader(ReceiptHeader $receipt): ReceiptHeader
    {
        return ReceiptHeader::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
    }

    private function assertEditable(ReceiptHeader $header): void
    {
        if (! $header->status->isEditable()) {
            throw new BusinessException("Dokumen berstatus {$header->status->label()} tidak bisa diubah.");
        }
    }

    private function assertTransition(ReceiptHeader $header, ReceiptStatus $to): void
    {
        if (! $header->status->canTransitionTo($to)) {
            throw new BusinessException("Status {$header->status->label()} tidak bisa diubah ke {$to->label()}.");
        }
    }
}
