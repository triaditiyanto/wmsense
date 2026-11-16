<?php

namespace Database\Seeders;

use App\Enums\LocationFunction;
use App\Enums\LpnType;
use App\Enums\PalletMaterial;
use App\Enums\RotationRule;
use App\Enums\TemperatureClass;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemClass;
use App\Models\ItemGroup;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\LocationClass;
use App\Models\LocationType;
use App\Models\Owner;
use App\Models\Pallet;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\LpnService;
use App\Support\WarehouseType;
use Illuminate\Database\Seeder;

/**
 * Master data dummy untuk site baru, disesuaikan tipe gudang (App\Support\WarehouseType).
 * Tujuannya agar user langsung punya contoh gudang/zona/lokasi/item yang bisa diubah, tanpa transaksi.
 * config('tenant.provision') = ['type' => WarehouseType, 'name' => nama site, 'company' => perusahaan, 'admin' => User]
 */
class TenantMasterSeeder extends Seeder
{
    public function run(): void
    {
        $p = config('tenant.provision', []);
        $type = $p['type'] ?? WarehouseType::GENERAL;
        $type = $type instanceof WarehouseType ? $type : WarehouseType::from($type);
        $profile = $type->profile();
        $company = $p['company'] ?? ($p['name'] ?? 'Perusahaan Anda');
        $admin = $p['admin'] ?? User::query()->first();

        // ---------------------------------------------------------------- gudang & zona
        $wh = Warehouse::updateOrCreate(['code' => 'WH1'], ['name' => 'Gudang Utama', 'address' => 'Alamat gudang (ubah di Master > Gudang)', 'profile' => $profile]);
        $zones = [];
        foreach ($type->temperatures() as $i => $temp) {
            $code = match ($temp) { TemperatureClass::AMBIENT => 'A', TemperatureClass::AIR_CONDITIONED => 'AC', TemperatureClass::CHILLED => 'C', TemperatureClass::FROZEN => 'F' };
            $zones[$temp->value] = Zone::updateOrCreate(['warehouse_id' => $wh->id, 'code' => $code], ['name' => 'Zona '.$code.' - '.$temp->label(), 'temperature_class' => $temp]);
        }
        $mainTemp = $type->temperatures()[0];
        $zoneMain = $zones[$mainTemp->value];
        $zoneDock = Zone::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'DOCK'], ['name' => 'Area Dock', 'temperature_class' => TemperatureClass::AMBIENT]);
        if ($profile['owners'] > 1) {
            // 3PL: zona per owner
            for ($i = 2; $i <= $profile['owners']; $i++) {
                $zones['OWN'.$i] = Zone::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'B'.($i - 1)], ['name' => 'Zona B'.($i - 1).' - klien '.$i, 'temperature_class' => $mainTemp]);
            }
        }

        // ---------------------------------------------------------------- tipe & kelas lokasi
        $types = [];
        $typeDefs = [
            ['RCV', 'Receiving Dock', LocationFunction::RECEIVING], ['STG', 'Storage', LocationFunction::STORAGE],
            ['PF', 'Pick Face', LocationFunction::PICK_FACE], ['STO', 'Staging Outbound', LocationFunction::STAGING_OUT],
            ['SHP', 'Shipping Dock', LocationFunction::SHIPPING],
        ];
        if ($profile['qc']) {
            $typeDefs[] = ['QC', 'Quality Control', LocationFunction::QC];
            $typeDefs[] = ['QRT', 'Karantina', LocationFunction::QUARANTINE];
        }
        foreach ($typeDefs as [$code, $name, $fn]) {
            $types[$code] = LocationType::updateOrCreate(['code' => $code], ['name' => $name, 'location_function' => $fn]);
        }
        $clsDock = LocationClass::updateOrCreate(['code' => 'DOCK'], ['location_type_id' => $types['RCV']->id, 'name' => 'Lantai Dock Inbound', 'storage_type' => 'floor', 'max_lpn' => 6]);
        $clsStage = LocationClass::updateOrCreate(['code' => 'STG-OUT'], ['location_type_id' => $types['STO']->id, 'name' => 'Lantai Staging Outbound', 'storage_type' => 'floor']);
        $clsShip = LocationClass::updateOrCreate(['code' => 'DOCK-OUT'], ['location_type_id' => $types['SHP']->id, 'name' => 'Lantai Dock Outbound', 'storage_type' => 'floor']);
        $clsRak = LocationClass::updateOrCreate(['code' => 'RAK-PLT'], [
            'location_type_id' => $types['STG']->id, 'name' => 'Rak Pallet 120x100x150', 'storage_type' => $type === WarehouseType::COLD ? 'drive_in' : 'selective_rack',
            'length_cm' => 120, 'width_cm' => 100, 'height_cm' => 150, 'max_weight_kg' => 1000, 'max_lpn' => 1,
            'allow_mixed_items' => false, 'allow_mixed_lots' => ! $profile['lot'],
        ]);
        $clsShelf = $profile['pick_face'] ? LocationClass::updateOrCreate(['code' => 'SHELF'], [
            'location_type_id' => $types['PF']->id, 'name' => 'Rak Shelving Pick Face', 'storage_type' => 'shelving',
            'length_cm' => 100, 'width_cm' => 50, 'height_cm' => 40, 'max_weight_kg' => 150,
        ]) : null;
        $clsQc = $profile['qc'] ? LocationClass::updateOrCreate(['code' => 'QC-AREA'], ['location_type_id' => $types['QC']->id, 'name' => 'Area Pemeriksaan', 'storage_type' => 'floor']) : null;
        $clsQrt = $profile['qc'] ? LocationClass::updateOrCreate(['code' => 'QRT-AREA'], ['location_type_id' => $types['QRT']->id, 'name' => 'Area Karantina', 'storage_type' => 'floor']) : null;

        // ---------------------------------------------------------------- lokasi
        foreach (['RCV-01', 'RCV-02'] as $code) {
            Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => $code], ['zone_id' => $zoneDock->id, 'location_class_id' => $clsDock->id]);
        }
        Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'STG-OUT-01'], ['zone_id' => $zoneDock->id, 'location_class_id' => $clsStage->id]);
        Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'SHP-01'], ['zone_id' => $zoneDock->id, 'location_class_id' => $clsShip->id]);
        if ($clsQc) {
            Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'QC-01'], ['zone_id' => $zoneDock->id, 'location_class_id' => $clsQc->id]);
            Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'QRT-01'], ['zone_id' => $zoneDock->id, 'location_class_id' => $clsQrt->id]);
        }
        // rak: tiap zona suhu/owner 1 aisle x 4 bay x 3 level (kode ZONA-AISLE-BAY-LEVEL)
        $seq = 0;
        $aisle = 1;
        foreach ($zones as $zone) {
            $a = sprintf('%02d', $aisle++);
            for ($bay = 1; $bay <= 4; $bay++) {
                for ($level = 1; $level <= 3; $level++) {
                    Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => sprintf('%s-%s-%02d-%d', $zone->code, $a, $bay, $level)], [
                        'zone_id' => $zone->id, 'location_class_id' => $clsRak->id,
                        'aisle' => $a, 'bay' => sprintf('%02d', $bay), 'level' => $level, 'pick_sequence' => ++$seq,
                    ]);
                }
            }
        }
        if ($clsShelf) {
            $n = $type === WarehouseType::ECOMMERCE ? 12 : 4;
            for ($i = 1; $i <= $n; $i++) {
                Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => sprintf('PF-%02d', $i)], [
                    'zone_id' => $zoneMain->id, 'location_class_id' => $clsShelf->id, 'aisle' => 'PF', 'bay' => sprintf('%02d', $i), 'level' => 1, 'pick_sequence' => 1000 + $i,
                ]);
            }
        }

        // ---------------------------------------------------------------- pallet & LPN
        $plt = Pallet::updateOrCreate(['code' => 'PLT-WD'], [
            'name' => 'Pallet Kayu 120x100', 'material' => PalletMaterial::WOOD,
            'length_cm' => 120, 'width_cm' => 100, 'height_cm' => 15, 'tare_weight_kg' => 25, 'max_load_kg' => 1000, 'max_stack_height_cm' => 165,
        ]);
        if ($admin && ! \App\Models\Lpn::exists()) {
            app(LpnService::class)->generate($wh, LpnType::PALLET, $plt->id, 10, $admin, 'Label awal');
        }

        // ---------------------------------------------------------------- owner, supplier, customer
        $owners = [Owner::updateOrCreate(['code' => 'OWN01'], ['name' => $company])];
        for ($i = 2; $i <= $profile['owners']; $i++) {
            $owners[] = Owner::updateOrCreate(['code' => sprintf('OWN%02d', $i)], ['name' => "Klien {$i} (contoh)"]);
        }
        Supplier::updateOrCreate(['code' => 'SUP001'], ['name' => 'PT Supplier Contoh', 'owner_id' => null, 'city' => 'Jakarta', 'pic' => 'Bapak Supplier', 'phone' => '021-0000000']);
        $cus = Customer::updateOrCreate(['code' => 'CUS001'], ['name' => 'PT Customer Contoh', 'owner_id' => null, 'city' => 'Jakarta']);
        $cus->addresses()->updateOrCreate(['label' => 'Gudang Utama'], ['address' => 'Jl. Contoh No. 1', 'city' => 'Jakarta', 'postal_code' => '10000', 'is_default' => true]);
        foreach (array_slice($owners, 1) as $i => $own) {
            Supplier::updateOrCreate(['code' => sprintf('SUP%03d', $i + 2)], ['name' => "Supplier {$own->name}", 'owner_id' => $own->id, 'city' => 'Bekasi']);
            $c = Customer::updateOrCreate(['code' => sprintf('CUS%03d', $i + 2)], ['name' => "Customer {$own->name}", 'owner_id' => $own->id, 'city' => 'Tangerang']);
            $c->addresses()->updateOrCreate(['label' => 'Toko'], ['address' => 'Jl. Contoh No. 2', 'city' => 'Tangerang', 'postal_code' => '15000', 'is_default' => true]);
        }

        // ---------------------------------------------------------------- UOM & item
        $uom = [];
        foreach ([['PCS', 'Pieces', false], ['BOX', 'Box', false], ['CTN', 'Karton', false], ['PLT', 'Pallet', false], ['KG', 'Kilogram', true]] as [$code, $name, $dec]) {
            $uom[$code] = Uom::updateOrCreate(['code' => $code], ['name' => $name, 'allow_decimal' => $dec]);
        }
        // [group code, group name, class code, class name, items [sku, name, berat kg, ctn factor]]
        $catalog = match ($type) {
            WarehouseType::FMCG => [
                ['FOOD', 'Makanan', 'SNACK', 'Snack', [['SNK-001', 'Keripik Kentang 68g', 0.08, 60], ['SNK-002', 'Biskuit Coklat 120g', 0.13, 24]]],
                ['BEV', 'Minuman', 'DRINK', 'Minuman Kemasan', [['DRK-001', 'Air Mineral 600ml', 0.62, 24], ['DRK-002', 'Susu UHT 1L (chiller)', 1.05, 12]]],
            ],
            WarehouseType::COLD => [
                ['FROZEN', 'Beku', 'MEAT', 'Daging & Ayam', [['FRZ-001', 'Ayam Potong 1kg', 1.0, 10], ['FRZ-002', 'Nugget 500g', 0.5, 20]]],
                ['CHILL', 'Dingin', 'DAIRY', 'Produk Susu', [['CHL-001', 'Yogurt 200ml', 0.21, 24], ['CHL-002', 'Keju 250g', 0.26, 20]]],
            ],
            WarehouseType::PHARMA => [
                ['DRUG', 'Obat', 'TABLET', 'Tablet & Kapsul', [['OBT-001', 'Paracetamol 500mg (strip 10)', 0.01, 100], ['OBT-002', 'Amoxicillin 500mg (strip 10)', 0.01, 100]]],
                ['MED', 'Alat Kesehatan', 'CONSUM', 'Habis Pakai', [['ALK-001', 'Masker Medis (box 50)', 0.15, 20], ['ALK-002', 'Sarung Tangan Nitril (box 100)', 0.5, 10]]],
            ],
            WarehouseType::ECOMMERCE => [
                ['FASHION', 'Fashion', 'APPAREL', 'Pakaian', [['FSH-001', 'Kaos Polos Hitam L', 0.2, 50], ['FSH-002', 'Celana Jeans 32', 0.6, 20]]],
                ['GADGET', 'Gadget & Aksesori', 'ACC', 'Aksesori', [['GDG-001', 'Kabel USB-C 1m', 0.05, 100], ['GDG-002', 'Powerbank 10000mAh', 0.25, 40]]],
                ['HOME', 'Rumah Tangga', 'KITCHEN', 'Dapur', [['HOM-001', 'Botol Minum 1L', 0.15, 24]]],
            ],
            WarehouseType::THREEPL => [
                ['GEN', 'Umum', 'GENCLS', 'Barang Umum', [['ITM-001', 'Barang Contoh Klien 1', 1.0, 12]]],
                ['HOME', 'Rumah Tangga', 'CLEAN', 'Pembersih', [['CLN-001', 'Sabun Cuci 800ml', 0.85, 12]]],
            ],
            default => [
                ['GEN', 'Umum', 'GENCLS', 'Barang Umum', [['ITM-001', 'Barang Contoh A', 1.0, 12], ['ITM-002', 'Barang Contoh B', 2.5, 6]]],
                ['SPARE', 'Spare Part', 'FILTER', 'Filter', [['SPR-001', 'Filter Oli Mobil', 0.4, 24]]],
            ],
        };
        $n = 0;
        foreach ($catalog as $gi => [$gCode, $gName, $cCode, $cName, $items]) {
            $group = ItemGroup::updateOrCreate(['code' => $gCode], ['name' => $gName]);
            $class = ItemClass::updateOrCreate(['code' => $cCode], ['item_group_id' => $group->id, 'name' => $cName]);
            $owner = $owners[min($gi, count($owners) - 1)];
            foreach ($items as [$sku, $name, $weight, $ctn]) {
                $ean = sprintf('899000000%03d', ++$n);
                $tempClass = match (true) {
                    str_contains($name, 'chiller') || $gCode === 'CHILL' => TemperatureClass::CHILLED,
                    $gCode === 'FROZEN' => TemperatureClass::FROZEN,
                    default => $mainTemp,
                };
                $item = Item::updateOrCreate(['owner_id' => $owner->id, 'sku' => $sku], [
                    'item_class_id' => $class->id, 'base_uom_id' => $uom['PCS']->id, 'name' => $name, 'barcode' => $ean,
                    'rotation_rule' => $type->rotation(), 'temperature_class' => $tempClass,
                    'is_lot_tracked' => $profile['lot'], 'is_expiry_tracked' => $profile['expiry'],
                    'shelf_life_days' => $profile['expiry'] ? 365 : null, 'min_shelf_life_days' => $profile['expiry'] ? ($type === WarehouseType::PHARMA ? 270 : 180) : null,
                    'weight_kg' => $weight,
                ]);
                ItemUom::updateOrCreate(['item_id' => $item->id, 'uom_id' => $uom['PCS']->id], ['conversion_factor' => 1, 'barcode' => $ean]);
                ItemUom::updateOrCreate(['item_id' => $item->id, 'uom_id' => $uom['CTN']->id], ['conversion_factor' => $ctn, 'barcode' => '1'.$ean, 'is_default_inbound' => true]);
            }
        }
    }
}
