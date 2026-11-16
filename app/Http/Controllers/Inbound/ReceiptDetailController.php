<?php

namespace App\Http\Controllers\Inbound;

use App\Http\Controllers\Controller;
use App\Models\ReceiptDetail;
use App\Models\ReceiptHeader;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Detail (item) dokumen receipt. Route-nya nested: /receipts/{receipt}/details/{detail} */
class ReceiptDetailController extends Controller
{
    public function __construct(private ReceiptService $service)
    {
    }

    public function store(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $detail = $this->service->addDetail($receipt, $this->validated($request), $request->user());

        return $this->ok("Item baris #{$detail->line_no} ditambahkan.");
    }

    public function update(Request $request, ReceiptHeader $receipt, ReceiptDetail $detail): JsonResponse
    {
        $this->service->updateDetail($detail, $this->validated($request), $request->user());

        return $this->ok("Item baris #{$detail->line_no} diperbarui.");
    }

    public function destroy(Request $request, ReceiptHeader $receipt, ReceiptDetail $detail): JsonResponse
    {
        $this->service->deleteDetail($detail, $request->user());

        return $this->ok("Item baris #{$detail->line_no} dihapus.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'item_id' => ['required', 'integer', 'exists:master_item,id'],
            'uom_id' => ['required', 'integer', 'exists:master_uom,id'],
            'qty_expected' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lot_no' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [], [
            'item_id' => 'item',
            'uom_id' => 'UOM',
            'qty_expected' => 'qty',
            'lot_no' => 'lot',
            'expiry_date' => 'tanggal expired',
        ]);
    }
}
