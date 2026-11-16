<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Owner;
use App\Models\ShipmentHeader;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShipmentService;
use App\Services\WaveService;
use Illuminate\Database\Seeder;

/**
 * Shipment contoh: 2 shipment dalam satu wave yang sudah dialokasikan (siap picking),
 * 1 shipment Open (untuk coba Wave Planning / Go Wave), 1 Draft (untuk coba entry).
 */
class DemoShipmentSeeder extends Seeder
{
    public function run(): void
    {
        if (ShipmentHeader::exists()) {
            return;
        }
        $wh = Warehouse::where('code', 'WH1')->firstOrFail();
        $admin = User::where('username', 'admin')->firstOrFail();
        $spv = User::where('username', 'spv')->firstOrFail();
        $own1 = Owner::where('code', 'OWN01')->firstOrFail();
        $own2 = Owner::where('code', 'OWN02')->firstOrFail();
        $ctn = Uom::where('code', 'CTN')->firstOrFail();
        $pcs = Uom::where('code', 'PCS')->firstOrFail();
        $cus = Customer::with('addresses')->get()->keyBy('code');
        $item = fn (string $sku) => Item::where('sku', $sku)->firstOrFail();
        $svc = app(ShipmentService::class);

        $make = function (Owner $owner, string $cusCode, ?string $addrLabel, array $lines, array $extra = []) use ($svc, $wh, $admin, $cus, $ctn, $pcs, $item) {
            $c = $cus[$cusCode];
            $h = $svc->createHeader([
                'warehouse_id' => $wh->id, 'owner_id' => $owner->id, 'customer_id' => $c->id,
                'customer_address_id' => $addrLabel ? $c->addresses->firstWhere('label', $addrLabel)?->id : null,
                'order_date' => now()->subDay()->format('Y-m-d'), 'expected_ship_date' => now()->format('Y-m-d'),
                ...$extra,
            ], $admin);
            foreach ($lines as [$sku, $qty, $uom]) {
                $svc->addDetail($h, ['item_id' => $item($sku)->id, 'uom_id' => $uom === 'CTN' ? $ctn->id : $pcs->id, 'qty_ordered' => $qty], $admin);
            }

            return $h;
        };

        $s1 = $make($own1, 'CUS001', 'DC Cikarang', [['SNK-001', 3, 'CTN'], ['DRK-001', 2, 'CTN'], ['NDL-001', 1, 'CTN']], ['reference_no' => 'SO-7781', 'priority' => 2]);
        $s2 = $make($own1, 'CUS002', 'Toko', [['SNK-002', 2, 'CTN'], ['DRK-002', 1, 'CTN']], ['reference_no' => 'SO-7782', 'allocation_rule' => 'FIFO']);
        $s3 = $make($own2, 'CUS003', 'Hub Kebon Jeruk', [['CLN-001', 4, 'CTN'], ['CLN-002', 2, 'CTN']], ['reference_no' => 'PO-MKT-0912', 'priority' => 3]);
        $make($own1, 'CUS001', 'Toko Kemang', [['SNK-001', 24, 'PCS']], ['reference_no' => 'SO-7790', 'notes' => 'Contoh dokumen Draft']);

        foreach ([$s1, $s2, $s3] as $s) {
            $svc->confirm($s, $spv);
        }
        // Wave contoh: 2 shipment, aturan FEFO, sudah dialokasikan -> siap picking
        $waves = app(WaveService::class);
        $wave = $waves->create(['warehouse_id' => $wh->id, 'allocation_rule' => 'FEFO', 'notes' => 'Wave contoh (demo)'], [$s1->id, $s2->id], $spv);
        $waves->allocate($wave, $spv);
    }
}
