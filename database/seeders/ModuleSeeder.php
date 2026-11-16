<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleDetail;
use Illuminate\Database\Seeder;

/**
 * Daftar module & module detail. Aman dijalankan ulang (updateOrCreate):
 *   php artisan db:seed --class=ModuleSeeder
 * route_name null = halaman belum dibuat (tidak muncul di sidebar, tapi sudah bisa diatur hak aksesnya).
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // menu_group = dropdown di navbar modul
            ['code' => 'MASTER', 'name' => 'Master Data', 'icon' => 'database', 'details' => [
                ['code' => 'MST_WAREHOUSE', 'name' => 'Gudang & Zona', 'route_name' => 'master.warehouses.index', 'menu_group' => 'Gudang'],
                ['code' => 'MST_LOC_TYPE', 'name' => 'Tipe Lokasi', 'route_name' => 'master.location-types.index', 'menu_group' => 'Gudang'],
                ['code' => 'MST_LOC_CLASS', 'name' => 'Kelas Lokasi', 'route_name' => 'master.location-classes.index', 'menu_group' => 'Gudang'],
                ['code' => 'MST_LOCATION', 'name' => 'Lokasi', 'route_name' => 'master.locations.index', 'menu_group' => 'Gudang'],
                // view = lihat & simulasi, edit = simpan aturan, delete = hapus aturan
                ['code' => 'MST_LOCATING', 'name' => 'Item Locating', 'route_name' => 'master.locating-rules.index', 'menu_group' => 'Gudang'],
                ['code' => 'MST_PALLET', 'name' => 'Jenis Pallet', 'route_name' => 'master.pallets.index', 'menu_group' => 'Gudang'],
                ['code' => 'MST_LPN', 'name' => 'LPN', 'route_name' => 'master.lpns.index', 'menu_group' => 'Gudang'],
                // view = cetak label LPN & lokasi (generate LPN baru butuh MST_LPN:create)
                ['code' => 'MST_LABEL', 'name' => 'Cetak Label', 'route_name' => 'master.labels.home', 'menu_group' => 'Gudang'],
                ['code' => 'MST_OWNER', 'name' => 'Owner', 'route_name' => 'master.owners.index', 'menu_group' => 'Mitra'],
                ['code' => 'MST_SUPPLIER', 'name' => 'Supplier', 'route_name' => 'master.suppliers.index', 'menu_group' => 'Mitra'],
                ['code' => 'MST_CUSTOMER', 'name' => 'Customer', 'route_name' => 'master.customers.index', 'menu_group' => 'Mitra'],
                ['code' => 'MST_ITEM_GROUP', 'name' => 'Item Group', 'route_name' => 'master.item-groups.index', 'menu_group' => 'Produk'],
                ['code' => 'MST_ITEM_CLASS', 'name' => 'Item Class', 'route_name' => 'master.item-classes.index', 'menu_group' => 'Produk'],
                ['code' => 'MST_ITEM', 'name' => 'Item', 'route_name' => 'master.items.index', 'menu_group' => 'Produk'],
                ['code' => 'MST_UOM', 'name' => 'UOM', 'route_name' => 'master.uoms.index', 'menu_group' => 'Produk'],
                ['code' => 'MST_USER', 'name' => 'User', 'route_name' => 'master.users.index', 'menu_group' => 'Pengguna'],
                ['code' => 'MST_ROLE', 'name' => 'Role & Hak Akses', 'route_name' => 'master.roles.index', 'menu_group' => 'Pengguna'],
            ]],
            ['code' => 'INBOUND', 'name' => 'Inbound', 'icon' => 'box-arrow-in-down', 'details' => [
                ['code' => 'INB_RECEIPT', 'name' => 'Receipt (ASN)', 'route_name' => 'inbound.receipts.index'],
                ['code' => 'INB_RECEIVING', 'name' => 'Receiving', 'route_name' => 'inbound.receiving.index'],
                // view = lihat barang di dock, delete = batal terima (qty kembali ke Receiving)
                ['code' => 'INB_INTRANSIT', 'name' => 'Intransit In', 'route_name' => 'inbound.intransit.index'],
                // create = kerjakan putaway, approve = setujui lokasi penuh / campur / suhu beda
                ['code' => 'INB_PUTAWAY', 'name' => 'Putaway', 'route_name' => 'inbound.putaway.index'],
                ['code' => 'INB_RPT_DETAIL', 'name' => 'Inbound Detail', 'route_name' => 'inbound.report-detail.index', 'menu_group' => 'Laporan'],
                ['code' => 'INB_RPT_SUMMARY', 'name' => 'Inbound Summary', 'route_name' => 'inbound.report-summary.index', 'menu_group' => 'Laporan'],
            ]],
            ['code' => 'INVENTORY', 'name' => 'Inventory', 'icon' => 'boxes', 'details' => [
                ['code' => 'INV_STOCK', 'name' => 'Inventory', 'route_name' => 'inventory.stock.index'],
                ['code' => 'INV_MAP3D', 'name' => 'Peta Gudang 3D', 'route_name' => 'inventory.map.index'],
                // create = pindah, approve = setujui peringatan (suhu / penuh / campur item)
                ['code' => 'INV_MOVE', 'name' => 'Pindah Stok', 'route_name' => 'inventory.move.index'],
                // create = koreksi qty / tambah temuan (alasan wajib)
                ['code' => 'INV_ADJUST', 'name' => 'Adjustment', 'route_name' => 'inventory.adjust.index'],
                // create = hold & release
                ['code' => 'INV_HOLD', 'name' => 'Hold & Release', 'route_name' => 'inventory.hold.index'],
            ]],
            ['code' => 'OUTBOUND', 'name' => 'Outbound', 'icon' => 'box-arrow-up', 'details' => [
                // create/edit/delete = header & detail saat Draft, approve = konfirmasi / batal
                ['code' => 'OUT_SHIPMENT', 'name' => 'Shipment', 'route_name' => 'outbound.shipments.index'],
                // create = buat wave, Run Wave & Go Wave; edit = alokasi ulang; delete = lepas shipment / batal wave
                ['code' => 'OUT_WAVE', 'name' => 'Wave Planning', 'route_name' => 'outbound.waves.index'],
                // create = kerjakan picking, approve = short pick / tutup baris belum dipick
                ['code' => 'OUT_PICKING', 'name' => 'Picking', 'route_name' => 'outbound.picking.index'],
                // create = buat Surat Jalan, edit = ubah / tambah shipment, delete = lepas / batal, approve = kirim
                ['code' => 'OUT_DISPATCH', 'name' => 'Dispatch & Surat Jalan', 'route_name' => 'outbound.dispatch.index'],
                ['code' => 'OUT_RPT_DETAIL', 'name' => 'Outbound Detail', 'route_name' => 'outbound.report-detail.index', 'menu_group' => 'Laporan'],
                ['code' => 'OUT_RPT_SUMMARY', 'name' => 'Outbound Summary', 'route_name' => 'outbound.report-summary.index', 'menu_group' => 'Laporan'],
            ]],
        ];

        // modul yang sudah dihapus dari daftar (hak aksesnya ikut dibersihkan)
        foreach (\App\Models\ModuleDetail::whereIn('code', ['INV_MOVEMENT'])->get() as $old) {
            \App\Models\RoleAccess::where('module_detail_id', $old->id)->delete();
            $old->delete();
        }

        foreach ($modules as $i => $m) {
            $module = Module::updateOrCreate(
                ['code' => $m['code']],
                ['name' => $m['name'], 'icon' => $m['icon'], 'sort_order' => ($i + 1) * 10],
            );

            foreach ($m['details'] as $j => $d) {
                ModuleDetail::updateOrCreate(
                    ['code' => $d['code']],
                    [...$d, 'module_id' => $module->id, 'sort_order' => ($j + 1) * 10],
                );
            }
        }
    }
}
