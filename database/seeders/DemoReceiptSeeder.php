<?php

namespace Database\Seeders;

use App\Enums\ReceiptType;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReceiptReceiving;
use App\Services\ReceivingService;
use App\Models\Owner;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReceiptService;
use Illuminate\Database\Seeder;

/** Contoh dokumen receipt: satu DRAFT dan satu OPEN. Dibuat lewat ReceiptService (aturan bisnis tetap berlaku). */
class DemoReceiptSeeder extends Seeder
{
    public function run(ReceiptService $service): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $wh = Warehouse::where('code', 'WH1')->firstOrFail();
        $owner = Owner::where('code', 'OWN01')->firstOrFail();
        $uom = Uom::pluck('id', 'code');
        $item = Item::where('owner_id', $owner->id)->pluck('id', 'sku');

        $docs = [
            ['PO-2611-0457', 'SUP001', 2, false, [
                ['SNK-001', 'CTN', 20, 'LT2611A', '2027-08-31'],
                ['NDL-001', 'CTN', 50, 'MG-0925', '2027-05-15'],
                ['DRK-001', 'PLT', 2, null, null],
            ]],
            ['PO-2611-0460', 'SUP002', 4, true, [
                ['SNK-002', 'CTN', 30, 'BC-1102', '2027-11-01'],
                ['DRK-002', 'CTN', 100, 'TB-2610', '2027-10-20'],
            ]],
        ];

        foreach ($docs as [$ref, $supplier, $days, $confirm, $lines]) {
            $receipt = $service->createHeader([
                'warehouse_id' => $wh->id,
                'owner_id' => $owner->id,
                'receipt_type' => ReceiptType::PURCHASE->value,
                'reference_no' => $ref,
                'supplier_id' => Supplier::where('code', $supplier)->value('id'),
                'expected_date' => now()->addDays($days)->format('Y-m-d'),
            ], $admin);

            foreach ($lines as [$sku, $uomCode, $qty, $lot, $exp]) {
                $service->addDetail($receipt, [
                    'item_id' => $item[$sku],
                    'uom_id' => $uom[$uomCode],
                    'qty_expected' => $qty,
                    'lot_no' => $lot,
                    'expiry_date' => $exp,
                ], $admin);
            }

            if ($confirm) {
                $service->confirm($receipt, $admin);
            }
        }

        $this->receivedDemo($service, $wh, $owner, $uom, $item);
    }

    /**
     * Receipt ke-3: sudah diterima penuh (2 pallet di RCV-02) lalu diselesaikan -> status RECEIVED.
     * Barang masih di dock -> tampil di Intransit In. Satu baris dimundurkan 6 jam (contoh "lama di dock").
     */
    private function receivedDemo(ReceiptService $service, Warehouse $wh, Owner $owner, $uom, $item): void
    {
        $receiving = app(ReceivingService::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $operator = User::where('username', 'operator')->firstOrFail();
        $spv = User::where('username', 'spv')->firstOrFail();
        $dock = Location::where('warehouse_id', $wh->id)->where('code', 'RCV-02')->firstOrFail();

        $receipt = $service->createHeader([
            'warehouse_id' => $wh->id, 'owner_id' => $owner->id, 'receipt_type' => ReceiptType::PURCHASE->value,
            'reference_no' => 'PO-2611-0471', 'supplier_id' => Supplier::where('code', 'SUP003')->value('id'),
            'expected_date' => now()->format('Y-m-d'),
        ], $admin);
        $lines = [
            // [sku, uom, qty, lot, expired, LPN]
            ['SNK-001', 'CTN', 10, 'LT2611B', '2027-08-31', 'LPN-WH1-26-000009'],
            ['NDL-001', 'CTN', 20, 'MG-1001', '2027-05-15', 'LPN-WH1-26-000010'],
        ];
        foreach ($lines as [$sku, $u, $qty, $lot, $exp]) {
            $service->addDetail($receipt, ['item_id' => $item[$sku], 'uom_id' => $uom[$u], 'qty_expected' => $qty, 'lot_no' => $lot, 'expiry_date' => $exp], $admin);
        }
        $service->confirm($receipt, $admin);

        foreach ($lines as [$sku, $u, $qty, $lot, $exp, $lpn]) {
            $receiving->receive($receipt, [
                'location_id' => $dock->id, 'lpn_no' => $lpn, 'item_id' => $item[$sku], 'uom_id' => $uom[$u],
                'qty' => $qty, 'lot_no' => $lot, 'expiry_date' => $exp, 'condition' => 'GOOD',
            ], $operator, ReceiptReceiving::SOURCE_MOBILE);
        }
        $receiving->finish($receipt, $spv);

        ReceiptReceiving::where('receipt_header_id', $receipt->id)->orderBy('id')->first()
            ->update(['received_at' => now()->subHours(6)]);
    }
}
