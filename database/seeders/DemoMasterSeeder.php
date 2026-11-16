<?php

namespace Database\Seeders;

use App\Enums\LocationFunction;
use App\Enums\LpnType;
use App\Enums\PalletMaterial;
use App\Models\Customer;
use App\Models\Lpn;
use App\Models\Supplier;
use App\Models\Pallet;
use App\Models\User;
use App\Services\LpnService;
use App\Enums\RotationRule;
use App\Enums\TemperatureClass;
use App\Models\Item;
use App\Models\ItemClass;
use App\Models\ItemGroup;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\LocationClass;
use App\Models\LocationType;
use App\Models\Owner;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/** Data master contoh supaya modul inbound bisa langsung dicoba. */
class DemoMasterSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------------------------------------------- warehouse & zona
        $wh = Warehouse::updateOrCreate(['code' => 'WH1'], ['name' => 'Gudang Utama Jakarta', 'address' => 'Jl. Industri No. 1, Jakarta']);
        $zoneA = Zone::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'A'], ['name' => 'Zona A - Ambient', 'temperature_class' => TemperatureClass::AMBIENT]);
        $zoneD = Zone::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'DOCK'], ['name' => 'Area Dock', 'temperature_class' => TemperatureClass::AMBIENT]);

        // ---------------------------------------------------- hierarki lokasi
        $types = [];
        foreach ([
            ['RCV', 'Receiving Dock', LocationFunction::RECEIVING],
            ['STG', 'Storage', LocationFunction::STORAGE],
            ['PF', 'Pick Face', LocationFunction::PICK_FACE],
            ['SHP', 'Shipping Dock', LocationFunction::SHIPPING],
            ['STO', 'Staging Outbound', LocationFunction::STAGING_OUT],
        ] as [$code, $name, $fn]) {
            $types[$code] = LocationType::updateOrCreate(['code' => $code], ['name' => $name, 'location_function' => $fn]);
        }

        $clsDock = LocationClass::updateOrCreate(['code' => 'DOCK'], ['location_type_id' => $types['RCV']->id, 'name' => 'Lantai Dock Inbound', 'storage_type' => 'floor', 'max_lpn' => 4]);
        $clsShip = LocationClass::updateOrCreate(['code' => 'DOCK-OUT'], ['location_type_id' => $types['SHP']->id, 'name' => 'Lantai Dock Outbound', 'storage_type' => 'floor']);
        $clsRak = LocationClass::updateOrCreate(['code' => 'RAK-PLT'], [
            'location_type_id' => $types['STG']->id, 'name' => 'Rak Pallet 120x100x150', 'storage_type' => 'selective_rack',
            'length_cm' => 120, 'width_cm' => 100, 'height_cm' => 150, 'max_weight_kg' => 1000, 'max_lpn' => 1,
            'allow_mixed_items' => false, 'allow_mixed_lots' => false,
        ]);
        $clsShelf = LocationClass::updateOrCreate(['code' => 'SHELF'], [
            'location_type_id' => $types['PF']->id, 'name' => 'Rak Shelving Pick Face', 'storage_type' => 'shelving',
            'length_cm' => 100, 'width_cm' => 50, 'height_cm' => 40, 'max_weight_kg' => 150,
        ]);

        foreach (['RCV-01', 'RCV-02'] as $code) {
            Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => $code], ['zone_id' => $zoneD->id, 'location_class_id' => $clsDock->id]);
        }
        Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => 'SHP-01'], ['zone_id' => $zoneD->id, 'location_class_id' => $clsShip->id]);
        // Staging outbound: hasil picking menunggu Surat Jalan / dispatch
        $clsStage = LocationClass::updateOrCreate(['code' => 'STG-OUT'], ['location_type_id' => $types['STO']->id, 'name' => 'Lantai Staging Outbound', 'storage_type' => 'floor']);
        foreach (['STG-OUT-01', 'STG-OUT-02'] as $code) {
            Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => $code], ['zone_id' => $zoneD->id, 'location_class_id' => $clsStage->id]);
        }

        $seq = 0;
        for ($bay = 1; $bay <= 5; $bay++) {
            for ($level = 1; $level <= 3; $level++) {
                $code = sprintf('A-01-%02d-%d', $bay, $level);
                Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => $code], [
                    'zone_id' => $zoneA->id, 'location_class_id' => $clsRak->id,
                    'aisle' => '01', 'bay' => sprintf('%02d', $bay), 'level' => $level, 'pick_sequence' => ++$seq,
                ]);
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => sprintf('PF-%02d', $i)], [
                'zone_id' => $zoneA->id, 'location_class_id' => $clsShelf->id, 'aisle' => 'PF', 'bay' => sprintf('%02d', $i), 'level' => 1, 'pick_sequence' => 100 + $i,
            ]);
        }
        // Aisle 02 & 03: 6 bay x 4 level (rak pallet) -> tampil sebagai baris rak di Peta Gudang 3D
        foreach (['02' => 200, '03' => 300] as $aisle => $base) {
            $n = 0;
            for ($bay = 1; $bay <= 6; $bay++) {
                for ($level = 1; $level <= 4; $level++) {
                    Location::updateOrCreate(['warehouse_id' => $wh->id, 'code' => sprintf('A-%s-%02d-%d', $aisle, $bay, $level)], [
                        'zone_id' => $zoneA->id, 'location_class_id' => $clsRak->id,
                        'aisle' => $aisle, 'bay' => sprintf('%02d', $bay), 'level' => $level, 'pick_sequence' => $base + (++$n),
                    ]);
                }
            }
        }

        // ---------------------------------------------------- jenis pallet & LPN
        $plt = Pallet::updateOrCreate(['code' => 'PLT-WD'], [
            'name' => 'Pallet Kayu 120x100', 'material' => PalletMaterial::WOOD,
            'length_cm' => 120, 'width_cm' => 100, 'height_cm' => 15, 'tare_weight_kg' => 25, 'max_load_kg' => 1000, 'max_stack_height_cm' => 165,
        ]);
        Pallet::updateOrCreate(['code' => 'PLT-PL'], [
            'name' => 'Pallet Plastik 120x100', 'material' => PalletMaterial::PLASTIC,
            'length_cm' => 120, 'width_cm' => 100, 'height_cm' => 14, 'tare_weight_kg' => 18, 'max_load_kg' => 1200, 'max_stack_height_cm' => 165,
        ]);
        if (! Lpn::exists()) {
            $superadmin = User::where('username', 'superadmin')->first();
            app(LpnService::class)->generate($wh, LpnType::PALLET, $plt->id, 10, $superadmin, 'Label demo');
        }

        // ---------------------------------------------------- owner & UOM
        $own1 = Owner::updateOrCreate(['code' => 'OWN01'], ['name' => 'PT Sumber Pangan Sejahtera']);
        $own2 = Owner::updateOrCreate(['code' => 'OWN02'], ['name' => 'PT Bersih Selalu (klien 3PL)']);

        // ---------------------------------------------------- supplier & customer
        // [kode, nama, owner (null = bersama), kota, pic, telepon]
        foreach ([
            ['SUP001', 'PT Sinar Distribusi', $own1, 'Jakarta Utara', 'Hendra', '021-4401122'],
            ['SUP002', 'CV Maju Bersama', $own1, 'Bekasi', 'Rina', '021-8899001'],
            ['SUP003', 'PT Kemasan Nusantara', null, 'Tangerang', 'Agus', '021-5523344'],
            ['SUP004', 'PT Kimia Bersih Abadi', $own2, 'Surabaya', 'Dewi', '031-7788990'],
        ] as [$code, $name, $owner, $city, $pic, $phone]) {
            Supplier::updateOrCreate(['code' => $code], ['name' => $name, 'owner_id' => $owner?->id, 'city' => $city, 'pic' => $pic, 'phone' => $phone]);
        }

        foreach ([
            ['CUS001', 'PT Retail Sejahtera', $own1, 'Jakarta Selatan', [
                ['DC Cikarang', 'Kawasan Industri Jababeka Blok A-12', 'Bekasi', '17530', true],
                ['Toko Kemang', 'Jl. Kemang Raya No. 8', 'Jakarta Selatan', '12730', false],
            ]],
            ['CUS002', 'Toko Berkah Jaya', $own1, 'Depok', [
                ['Toko', 'Jl. Margonda Raya No. 120', 'Depok', '16424', true],
            ]],
            ['CUS003', 'PT Marketplace Indonesia', null, 'Jakarta Barat', [
                ['Hub Kebon Jeruk', 'Jl. Panjang No. 5', 'Jakarta Barat', '11530', true],
            ]],
        ] as [$code, $name, $owner, $city, $addresses]) {
            $customer = Customer::updateOrCreate(['code' => $code], ['name' => $name, 'owner_id' => $owner?->id, 'city' => $city]);
            foreach ($addresses as [$label, $addr, $acity, $postal, $default]) {
                $customer->addresses()->updateOrCreate(['label' => $label], [
                    'address' => $addr, 'city' => $acity, 'postal_code' => $postal, 'is_default' => $default,
                ]);
            }
        }

        $uom = [];
        foreach ([['PCS', 'Pieces', false], ['BOX', 'Box', false], ['CTN', 'Karton', false], ['PLT', 'Pallet', false], ['KG', 'Kilogram', true]] as [$code, $name, $dec]) {
            $uom[$code] = Uom::updateOrCreate(['code' => $code], ['name' => $name, 'allow_decimal' => $dec]);
        }

        // ---------------------------------------------------- hierarki item
        $grpFood = ItemGroup::updateOrCreate(['code' => 'FOOD'], ['name' => 'Makanan']);
        $grpBev = ItemGroup::updateOrCreate(['code' => 'BEV'], ['name' => 'Minuman']);
        $grpHome = ItemGroup::updateOrCreate(['code' => 'HOME'], ['name' => 'Rumah Tangga']);

        $clsSnack = ItemClass::updateOrCreate(['code' => 'SNACK'], ['item_group_id' => $grpFood->id, 'name' => 'Snack']);
        $clsNoodle = ItemClass::updateOrCreate(['code' => 'NOODLE'], ['item_group_id' => $grpFood->id, 'name' => 'Mie Instan']);
        $clsDrink = ItemClass::updateOrCreate(['code' => 'DRINK'], ['item_group_id' => $grpBev->id, 'name' => 'Minuman Kemasan']);
        $clsClean = ItemClass::updateOrCreate(['code' => 'CLEAN'], ['item_group_id' => $grpHome->id, 'name' => 'Pembersih']);

        $items = [
            // [owner, class, sku, name, lot, exp, shelf_life, uoms [code => factor]]
            [$own1, $clsSnack, 'SNK-001', 'Keripik Kentang 68g', true, true, 270, ['BOX' => 10, 'CTN' => 60]],
            [$own1, $clsSnack, 'SNK-002', 'Biskuit Coklat 120g', true, true, 365, ['CTN' => 24]],
            [$own1, $clsNoodle, 'NDL-001', 'Mie Goreng 85g', true, true, 240, ['CTN' => 40]],
            [$own1, $clsDrink, 'DRK-001', 'Air Mineral 600ml', true, true, 730, ['CTN' => 24, 'PLT' => 1440]],
            [$own1, $clsDrink, 'DRK-002', 'Teh Botol 350ml', true, true, 365, ['CTN' => 24]],
            [$own2, $clsClean, 'CLN-001', 'Sabun Cuci Piring 800ml', false, false, null, ['CTN' => 12]],
            [$own2, $clsClean, 'CLN-002', 'Pembersih Lantai 1L', true, false, null, ['CTN' => 12]],
        ];

        // Barcode demo: satuan = 899100000NNN, kemasan = 1899100000NNN + nomor kemasan (pola ITF/GTIN-14 sederhana)
        foreach ($items as $n => [$owner, $class, $sku, $name, $lot, $exp, $shelf, $uoms]) {
            $ean = sprintf('899100000%03d', $n + 1);
            $item = Item::updateOrCreate(['owner_id' => $owner->id, 'sku' => $sku], [
                'item_class_id' => $class->id, 'base_uom_id' => $uom['PCS']->id, 'name' => $name, 'barcode' => $ean,
                'rotation_rule' => $exp ? RotationRule::FEFO : RotationRule::FIFO,
                'is_lot_tracked' => $lot, 'is_expiry_tracked' => $exp,
                'shelf_life_days' => $shelf, 'min_shelf_life_days' => $shelf ? (int) round($shelf * 0.6) : null,
            ]);
            ItemUom::updateOrCreate(['item_id' => $item->id, 'uom_id' => $uom['PCS']->id], ['conversion_factor' => 1, 'barcode' => $ean]);
            $k = 1;
            foreach ($uoms as $code => $factor) {
                ItemUom::updateOrCreate(['item_id' => $item->id, 'uom_id' => $uom[$code]->id], [
                    'conversion_factor' => $factor,
                    'barcode' => $k++.$ean,
                    'is_default_inbound' => $code === 'CTN',
                ]);
            }
        }
    }
}
