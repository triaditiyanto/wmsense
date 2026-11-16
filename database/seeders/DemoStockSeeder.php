<?php

namespace Database\Seeders;

use App\Enums\InventoryStatus;
use App\Enums\LpnStatus;
use App\Enums\LpnType;
use App\Enums\MovementType;
use App\Models\Item;
use App\Models\Location;
use App\Models\Pallet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Services\InventoryService;
use App\Services\LpnService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Saldo awal demo di rak (ADJUST_IN, alasan OPENING) supaya Inventory & Peta Gudang 3D berisi:
 * pallet di aisle 01-03 (umur & expired bervariasi, 1 pallet rusak, 1 lot kedaluwarsa) + stok lepas di pick face.
 * Slot A-01-01-x dan A-01-05-x dibiarkan kosong (dipakai contoh putaway). Hasil acak tapi tetap (mt_srand).
 */
class DemoStockSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('o_inventory_movement')->where('reason_code', 'OPENING')->exists()) {
            return;
        }
        mt_srand(2611);
        $wh = Warehouse::where('code', 'WH1')->firstOrFail();
        $by = User::where('username', 'superadmin')->firstOrFail();
        $inv = app(InventoryService::class);
        $items = Item::with('uoms')->whereIn('sku', ['SNK-001', 'SNK-002', 'NDL-001', 'DRK-001', 'DRK-002', 'CLN-001', 'CLN-002'])->get()->keyBy('sku');
        $ctnFactor = fn (Item $i) => (int) ($i->uoms->firstWhere('is_default_inbound', true)?->conversion_factor ?? 12);

        $slots = Location::where('warehouse_id', $wh->id)->whereIn('aisle', ['01', '02', '03'])
            ->orderBy('pick_sequence')->get()
            ->filter(fn ($l) => ! ($l->aisle === '01' && in_array($l->bay, ['01', '05'], true)))
            ->filter(fn () => mt_rand(1, 100) <= 62)->values();
        $lpns = app(LpnService::class)->generate($wh, LpnType::PALLET, Pallet::where('code', 'PLT-WD')->value('id'), $slots->count(), $by, 'Saldo awal demo');

        // item per aisle: aisle 01-02 makanan/minuman, aisle 03 lebih banyak pembersih (owner 3PL)
        $pool = ['01' => ['SNK-001', 'SNK-002', 'NDL-001', 'DRK-001'], '02' => ['DRK-001', 'DRK-002', 'NDL-001', 'SNK-002'], '03' => ['CLN-001', 'CLN-002', 'CLN-001', 'DRK-002']];
        foreach ($slots as $n => $loc) {
            $item = $items[$pool[$loc->aisle][mt_rand(0, 3)]];
            $lpn = $lpns[$n];
            $age = mt_rand(1, 120);
            $exp = $item->is_expiry_tracked ? now()->addDays(match (true) {
                $n === 3 => -6,                       // satu lot sudah kedaluwarsa
                $n % 9 === 4 => mt_rand(8, 28),       // mendekati expired
                default => mt_rand(60, 400),
            })->format('Y-m-d') : null;
            $status = $n === 7 ? InventoryStatus::DAMAGED : InventoryStatus::AVAILABLE;
            $qty = $ctnFactor($item) * mt_rand(20, 48);

            $this->put($inv, $by, new StockAttributes($wh->id, $loc->id, $item->id, $item->owner_id,
                $item->is_lot_tracked ? 'LT'.now()->subDays($age)->format('ymd') : null, $exp, $lpn->id, $status), $qty, $age);
            $lpn->update(['status' => LpnStatus::ACTIVE, 'location_id' => $loc->id]);
        }

        // stok lepas (tanpa LPN) di pick face
        foreach (['PF-01' => 'SNK-001', 'PF-02' => 'SNK-002', 'PF-03' => 'DRK-001', 'PF-04' => 'CLN-001'] as $code => $sku) {
            $loc = Location::where('warehouse_id', $wh->id)->where('code', $code)->firstOrFail();
            $item = $items[$sku];
            $this->put($inv, $by, new StockAttributes($wh->id, $loc->id, $item->id, $item->owner_id,
                $item->is_lot_tracked ? 'LT'.now()->subDays(10)->format('ymd') : null,
                $item->is_expiry_tracked ? now()->addDays(150)->format('Y-m-d') : null), mt_rand(30, 120), 10);
        }
    }

    private function put(InventoryService $inv, User $by, StockAttributes $attr, int $qty, int $ageDays): void
    {
        DB::transaction(fn () => $inv->changeOnHand($attr, $qty, MovementType::ADJUST_IN,
            new MovementContext(userId: $by->id, reasonCode: 'OPENING', notes: 'Saldo awal demo'), now()->subDays($ageDays)));
    }
}
