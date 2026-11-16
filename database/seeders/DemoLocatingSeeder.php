<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemClass;
use App\Models\ItemGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\LocatingRuleService;
use Illuminate\Database\Seeder;

/**
 * Contoh aturan Item Locating (Master > Item Locating):
 *   - Item Group Rumah Tangga -> hanya Aisle 03 (ketat), level rendah dulu
 *   - Item Class Minuman Kemasan -> Aisle 02 level 1-2, lalu Aisle 01
 *   - Item SNK-001 -> Aisle 01, lalu seluruh Zona A
 */
class DemoLocatingSeeder extends Seeder
{
    public function run(): void
    {
        $wh = Warehouse::where('code', 'WH1')->firstOrFail();
        $zoneA = Zone::where('warehouse_id', $wh->id)->where('code', 'A')->firstOrFail();
        $by = User::where('username', 'superadmin')->firstOrFail();
        $svc = app(LocatingRuleService::class);
        $aisle = fn ($a, $min = null, $max = null) => ['target_type' => 'AISLE', 'zone_id' => $zoneA->id, 'aisle' => $a, 'level_min' => $min, 'level_max' => $max];

        $svc->save(['warehouse_id' => $wh->id, 'scope_type' => 'ITEM_GROUP', 'scope_id' => ItemGroup::where('code', 'HOME')->value('id'),
            'level_preference' => 'LOW', 'prefer_consolidate' => true, 'strict' => true, 'is_active' => true,
            'notes' => 'Bahan kimia rumah tangga dipisah di aisle 03', 'targets' => [$aisle('03')]], $by);
        $svc->save(['warehouse_id' => $wh->id, 'scope_type' => 'ITEM_CLASS', 'scope_id' => ItemClass::where('code', 'DRINK')->value('id'),
            'level_preference' => 'LOW', 'prefer_consolidate' => true, 'strict' => false, 'is_active' => true,
            'notes' => 'Minuman berat: level bawah', 'targets' => [$aisle('02', 1, 2), $aisle('01')]], $by);
        $svc->save(['warehouse_id' => $wh->id, 'scope_type' => 'ITEM', 'scope_id' => Item::where('sku', 'SNK-001')->value('id'),
            'level_preference' => null, 'prefer_consolidate' => true, 'strict' => false, 'is_active' => true,
            'notes' => 'Fast moving dekat dock', 'targets' => [$aisle('01'), ['target_type' => 'ZONE', 'zone_id' => $zoneA->id]]], $by);
    }
}
