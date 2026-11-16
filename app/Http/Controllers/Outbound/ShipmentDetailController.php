<?php

namespace App\Http\Controllers\Outbound;

use App\Http\Controllers\Controller;
use App\Models\ShipmentDetail;
use App\Models\ShipmentHeader;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Detail (item) shipment. Route nested: /shipments/{shipment}/details/{detail} */
class ShipmentDetailController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function store(Request $request, ShipmentHeader $shipment): JsonResponse
    {
        $detail = $this->service->addDetail($shipment, $this->validated($request), $request->user());

        return $this->ok("Item baris #{$detail->line_no} ditambahkan.");
    }

    public function update(Request $request, ShipmentHeader $shipment, ShipmentDetail $detail): JsonResponse
    {
        $this->service->updateDetail($detail, $this->validated($request), $request->user());

        return $this->ok("Item baris #{$detail->line_no} diperbarui.");
    }

    public function destroy(Request $request, ShipmentHeader $shipment, ShipmentDetail $detail): JsonResponse
    {
        $this->service->deleteDetail($detail, $request->user());

        return $this->ok("Item baris #{$detail->line_no} dihapus.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'item_id' => ['required', 'integer', 'exists:master_item,id'],
            'uom_id' => ['required', 'integer', 'exists:master_uom,id'],
            'qty_ordered' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lot_no' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ], [], ['item_id' => 'item', 'uom_id' => 'UOM', 'qty_ordered' => 'qty', 'lot_no' => 'lot']);
    }
}
