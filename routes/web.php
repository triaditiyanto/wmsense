<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inbound\IntransitController;
use App\Http\Controllers\Inbound\PutawayController;
use App\Http\Controllers\Inbound\ReceiptController;
use App\Http\Controllers\Inbound\ReceiptDetailController;
use App\Http\Controllers\Inbound\ReceiptLabelController;
use App\Http\Controllers\Inbound\ReceivingController;
use App\Http\Controllers\Inventory\StockControlController;
use App\Http\Controllers\Inventory\WarehouseMapController;
use App\Http\Controllers\Mobile\MobileController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\Master\CustomerController;
use App\Http\Controllers\Master\ItemClassController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\ItemGroupController;
use App\Http\Controllers\Master\LabelController;
use App\Http\Controllers\Master\LocatingRuleController;
use App\Http\Controllers\Master\LocationClassController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\LocationTypeController;
use App\Http\Controllers\Master\LpnController;
use App\Http\Controllers\Master\OwnerController;
use App\Http\Controllers\Master\PalletController;
use App\Http\Controllers\Master\RoleController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UomController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\Master\WarehouseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Outbound\DispatchController;
use App\Http\Controllers\Outbound\PickingController;
use App\Http\Controllers\Outbound\ShipmentController;
use App\Http\Controllers\Outbound\ShipmentDetailController;
use App\Http\Controllers\Outbound\WaveController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Cara baca: Route::method('url', [Controller::class, 'method'])
|   ->middleware('access:KODE_MODULE_DETAIL,aksi')   // cek hak akses
|   ->name('nama.route');                            // dipanggil dengan route('nama.route')
| Lihat semua route: php artisan route:list
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

// ------------------------------------------------------------ Portal SaaS (di luar WMS, tanpa login WMS)
Route::prefix('portal')->name('portal.')->controller(PortalController::class)->group(function () {
    Route::get('/', 'landing')->name('landing');
    Route::get('register', 'register')->name('register');
    Route::post('register', 'store')->middleware('throttle:5,10')->name('store');
    Route::get('registered/{code}', 'registered')->name('registered');
    Route::match(['get', 'post'], 'monitor/login', 'monitorLogin')->middleware('throttle:10,1')->name('monitor.login');
    Route::post('monitor/logout', 'monitorLogout')->name('monitor.logout');
    Route::get('monitor', 'monitor')->name('monitor');
});

/*
| Laporan berbasis App\Reports\Report (tabel + pivot + grafik + export Excel). Satu set route per laporan:
|   GET  {uri}               -> index        name: {name}.index
|   GET  {uri}/dataset       -> dataset      name: {name}.dataset
|   GET  {uri}/export        -> export       name: {name}.export
|   POST {uri}/export-pivot  -> exportPivot  name: {name}.export-pivot
|   GET  {uri}/row           -> row          name: {name}.row (detail baris, bila laporan punya rowKey())
| $key = key di ReportController::REPORTS, $code = kode hak akses (view).
*/
$report = function (string $uri, string $name, string $key, string $code): void {
    Route::controller(ReportController::class)->prefix($uri)->name("{$name}.")
        ->middleware("access:{$code},view")->group(function () use ($key) {
            Route::get('/', 'index')->defaults('report', $key)->name('index');
            Route::get('dataset', 'dataset')->defaults('report', $key)->name('dataset');
            Route::get('export', 'export')->defaults('report', $key)->name('export');
            Route::post('export-pivot', 'exportPivot')->defaults('report', $key)->name('export-pivot');
            Route::get('row', 'row')->defaults('report', $key)->name('row');
        });
};

Route::middleware('auth')->group(function () use ($report) {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifikasi (lonceng)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Pencarian untuk dropdown di dokumen transaksi (inbound & outbound)
    Route::get('/lookup/items', [LookupController::class, 'items'])->name('lookup.items');
    Route::get('/lookup/suppliers', [LookupController::class, 'suppliers'])->name('lookup.suppliers');
    Route::get('/lookup/customers', [LookupController::class, 'customers'])->name('lookup.customers');

    // ------------------------------------------------------------ Master
    Route::prefix('master')->name('master.')->group(function () {
        Route::controller(UserController::class)->group(function () {
            Route::get('users', 'index')->middleware('access:MST_USER,view')->name('users.index');
            Route::post('users', 'store')->middleware('access:MST_USER,create')->name('users.store');
            Route::put('users/{user}', 'update')->middleware('access:MST_USER,edit')->name('users.update');
        });

        Route::controller(RoleController::class)->group(function () {
            Route::get('roles', 'index')->middleware('access:MST_ROLE,view')->name('roles.index');
            Route::post('roles', 'store')->middleware('access:MST_ROLE,create')->name('roles.store');
            Route::put('roles/{role}', 'update')->middleware('access:MST_ROLE,edit')->name('roles.update');
            Route::get('roles/{role}/access', 'access')->middleware('access:MST_ROLE,view')->name('roles.access');
            Route::put('roles/{role}/access', 'saveAccess')->middleware('access:MST_ROLE,edit')->name('roles.access.save');
        });

        /*
         * Master sederhana: list + tambah + ubah. Helper ini membuat 3 route sekaligus:
         *   GET  master/{uri}           -> index   (access:KODE,view)   name: master.{uri}.index
         *   POST master/{uri}           -> store   (access:KODE,create) name: master.{uri}.store
         *   PUT  master/{uri}/{param}   -> update  (access:KODE,edit)   name: master.{uri}.update
         * {param} harus sama dengan nama variabel di method update(), mis. LocationType $locationType.
         */
        $crud = function (string $uri, string $controller, string $code, string $param): void {
            Route::controller($controller)->group(function () use ($uri, $code, $param) {
                Route::get($uri, 'index')->middleware("access:{$code},view")->name("{$uri}.index");
                Route::post($uri, 'store')->middleware("access:{$code},create")->name("{$uri}.store");
                Route::put("{$uri}/{{$param}}", 'update')->middleware("access:{$code},edit")->name("{$uri}.update");
            });
        };

        // ---- Gudang
        $crud('warehouses', WarehouseController::class, 'MST_WAREHOUSE', 'warehouse');
        Route::controller(WarehouseController::class)->scopeBindings()->group(function () {
            Route::get('warehouses/{warehouse}', 'show')->middleware('access:MST_WAREHOUSE,view')->name('warehouses.show');
            Route::post('warehouses/{warehouse}/zones', 'storeZone')->middleware('access:MST_WAREHOUSE,create')->name('warehouses.zones.store');
            Route::put('warehouses/{warehouse}/zones/{zone}', 'updateZone')->middleware('access:MST_WAREHOUSE,edit')->name('warehouses.zones.update');
        });
        $crud('location-types', LocationTypeController::class, 'MST_LOC_TYPE', 'locationType');
        $crud('location-classes', LocationClassController::class, 'MST_LOC_CLASS', 'locationClass');
        $crud('locations', LocationController::class, 'MST_LOCATION', 'location');
        Route::post('locations/generate', [LocationController::class, 'generate'])->middleware('access:MST_LOCATION,create')->name('locations.generate');
        $crud('pallets', PalletController::class, 'MST_PALLET', 'pallet');
        $crud('lpns', LpnController::class, 'MST_LPN', 'lpn');
        Route::post('lpns/generate', [LpnController::class, 'generate'])->middleware('access:MST_LPN,create')->name('lpns.generate');
        Route::post('lpns/{lpn}/void', [LpnController::class, 'void'])->middleware('access:MST_LPN,delete')->name('lpns.void');

        // ---- Item Locating: aturan area simpan putaway per Item / Item Class / Item Group (editor drag & drop)
        Route::controller(LocatingRuleController::class)->prefix('locating-rules')->name('locating-rules.')->group(function () {
            Route::get('/', 'index')->middleware('access:MST_LOCATING,view')->name('index');
            Route::get('data', 'data')->middleware('access:MST_LOCATING,view')->name('data');
            Route::post('simulate', 'simulate')->middleware('access:MST_LOCATING,view')->name('simulate');
            Route::put('/', 'save')->middleware('access:MST_LOCATING,edit')->name('save');
            Route::delete('{rule}', 'destroy')->middleware('access:MST_LOCATING,delete')->name('destroy');
        });

        // ---- Cetak label LPN & lokasi (QR / barcode)
        Route::controller(LabelController::class)->prefix('labels')->name('labels.')->group(function () {
            Route::get('/', fn () => redirect()->route('master.labels.index', 'lpn'))->middleware('access:MST_LABEL,view')->name('home');
            Route::match(['get', 'post'], 'print', 'print')->middleware('access:MST_LABEL,view')->name('print');
            Route::post('generate', 'generate')->middleware('access:MST_LPN,create')->name('generate');
            Route::get('{type}', 'index')->whereIn('type', ['lpn', 'location'])->middleware('access:MST_LABEL,view')->name('index');
        });

        // ---- Mitra
        $crud('owners', OwnerController::class, 'MST_OWNER', 'owner');
        $crud('suppliers', SupplierController::class, 'MST_SUPPLIER', 'supplier');
        $crud('customers', CustomerController::class, 'MST_CUSTOMER', 'customer');
        Route::controller(CustomerController::class)->scopeBindings()->group(function () {
            Route::get('customers/{customer}', 'show')->middleware('access:MST_CUSTOMER,view')->name('customers.show');
            Route::post('customers/{customer}/addresses', 'storeAddress')->middleware('access:MST_CUSTOMER,create')->name('customers.addresses.store');
            Route::put('customers/{customer}/addresses/{address}', 'updateAddress')->middleware('access:MST_CUSTOMER,edit')->name('customers.addresses.update');
            Route::delete('customers/{customer}/addresses/{address}', 'destroyAddress')->middleware('access:MST_CUSTOMER,delete')->name('customers.addresses.destroy');
        });

        // ---- Produk
        $crud('item-groups', ItemGroupController::class, 'MST_ITEM_GROUP', 'itemGroup');
        $crud('item-classes', ItemClassController::class, 'MST_ITEM_CLASS', 'itemClass');
        $crud('uoms', UomController::class, 'MST_UOM', 'uom');
        $crud('items', ItemController::class, 'MST_ITEM', 'item');
        Route::controller(ItemController::class)->scopeBindings()->group(function () {
            Route::get('items/{item}', 'show')->middleware('access:MST_ITEM,view')->name('items.show');
            // {uom} = baris master_item_uom milik {item} (relasi Item::uoms)
            Route::post('items/{item}/uoms', 'storeUom')->middleware('access:MST_ITEM,create')->name('items.uoms.store');
            Route::put('items/{item}/uoms/{uom}', 'updateUom')->middleware('access:MST_ITEM,edit')->name('items.uoms.update');
            Route::delete('items/{item}/uoms/{uom}', 'destroyUom')->middleware('access:MST_ITEM,delete')->name('items.uoms.destroy');
        });
    });

    // ------------------------------------------------------------ Inbound
    Route::prefix('inbound')->name('inbound.')->group(function () use ($report) {
        Route::controller(ReceiptController::class)->group(function () {
            Route::get('receipts', 'index')->middleware('access:INB_RECEIPT,view')->name('receipts.index');
            Route::post('receipts', 'store')->middleware('access:INB_RECEIPT,create')->name('receipts.store');
            Route::get('receipts/{receipt}', 'show')->middleware('access:INB_RECEIPT,view')->name('receipts.show');
            Route::put('receipts/{receipt}', 'update')->middleware('access:INB_RECEIPT,edit')->name('receipts.update');
            Route::post('receipts/{receipt}/confirm', 'confirm')->middleware('access:INB_RECEIPT,approve')->name('receipts.confirm');
            Route::post('receipts/{receipt}/cancel', 'cancel')->middleware('access:INB_RECEIPT,approve')->name('receipts.cancel');
        });

        // Cetak label LPN dari dokumen (LPN yang dipakai / disiapkan). Generate dicek di controller (INB_RECEIVING / MST_LPN create)
        Route::controller(ReceiptLabelController::class)->group(function () {
            Route::match(['get', 'post'], 'receipts/{receipt}/labels', 'print')->middleware('access:INB_RECEIPT,view')->name('receipts.labels');
            Route::post('receipts/{receipt}/labels/generate', 'generate')->middleware('access:INB_RECEIPT,view')->name('receipts.labels.generate');
        });

        // scopeBindings: {detail} wajib milik {receipt}, kalau tidak -> 404
        Route::controller(ReceiptDetailController::class)->scopeBindings()->group(function () {
            Route::post('receipts/{receipt}/details', 'store')->middleware('access:INB_RECEIPT,create')->name('receipts.details.store');
            Route::put('receipts/{receipt}/details/{detail}', 'update')->middleware('access:INB_RECEIPT,edit')->name('receipts.details.update');
            Route::delete('receipts/{receipt}/details/{detail}', 'destroy')->middleware('access:INB_RECEIPT,delete')->name('receipts.details.destroy');
        });

        // Receiving: halaman desktop + endpoint JSON (dipakai juga oleh tampilan mobile /m)
        Route::controller(ReceivingController::class)->prefix('receiving')->name('receiving.')->scopeBindings()->group(function () {
            Route::get('/', 'index')->middleware('access:INB_RECEIVING,view')->name('index');
            Route::get('{receipt}', 'show')->middleware('access:INB_RECEIVING,view')->name('show');
            Route::get('{receipt}/progress', 'progress')->middleware('access:INB_RECEIVING,view')->name('progress');
            Route::get('{receipt}/docks', 'dockStatus')->middleware('access:INB_RECEIVING,view')->name('docks');
            Route::post('{receipt}/docks/notify-full', 'notifyDockFull')->middleware('access:INB_RECEIVING,create')->name('docks.notify');
            Route::get('{receipt}/resolve/location', 'resolveLocation')->middleware('access:INB_RECEIVING,view')->name('resolve.location');
            Route::get('{receipt}/resolve/lpn', 'resolveLpn')->middleware('access:INB_RECEIVING,view')->name('resolve.lpn');
            Route::get('{receipt}/resolve/item', 'resolveItem')->middleware('access:INB_RECEIVING,view')->name('resolve.item');
            Route::post('{receipt}/receive', 'receive')->middleware('access:INB_RECEIVING,create')->name('receive');
            Route::post('{receipt}/receivings/{receiving}/cancel', 'cancelLine')->middleware('access:INB_RECEIVING,delete')->name('cancel');
            Route::post('{receipt}/finish', 'finish')->middleware('access:INB_RECEIVING,approve')->name('finish');
        });

        // Intransit In: barang sudah diterima, belum disimpan (masih di dock)
        Route::controller(IntransitController::class)->prefix('intransit')->name('intransit.')->group(function () {
            Route::get('/', 'index')->middleware('access:INB_INTRANSIT,view')->name('index');
            Route::post('cancel', 'cancel')->middleware('access:INB_INTRANSIT,delete')->name('cancel');
        });

        // Putaway: halaman desktop + endpoint JSON (dipakai juga oleh /m/putaway). {task} = o_task_header
        Route::controller(PutawayController::class)->prefix('putaway')->name('putaway.')->group(function () {
            Route::get('/', 'index')->middleware('access:INB_PUTAWAY,view')->name('index');
            Route::post('start', 'start')->middleware('access:INB_PUTAWAY,create')->name('start');
            Route::get('tasks/{task}', 'show')->middleware('access:INB_PUTAWAY,view')->name('show');
            Route::get('tasks/{task}/resolve-location', 'resolveLocation')->middleware('access:INB_PUTAWAY,view')->name('resolve.location');
            Route::post('tasks/{task}/complete', 'complete')->middleware('access:INB_PUTAWAY,create')->name('complete');
            Route::post('tasks/{task}/cancel', 'cancel')->middleware('access:INB_PUTAWAY,create')->name('cancel');
        });

        // Laporan inbound (helper $report di atas)
        $report('reports/detail', 'report-detail', 'detail', 'INB_RPT_DETAIL');
        $report('reports/summary', 'report-summary', 'summary', 'INB_RPT_SUMMARY');
    });

    // ------------------------------------------------------------ Outbound
    Route::prefix('outbound')->name('outbound.')->group(function () use ($report) {
        // Laporan outbound (helper $report di atas)
        $report('reports/detail', 'report-detail', 'out-detail', 'OUT_RPT_DETAIL');
        $report('reports/summary', 'report-summary', 'out-summary', 'OUT_RPT_SUMMARY');

        // Shipment entry (header + detail) + Go Wave (wave 1 shipment, butuh hak buat wave)
        Route::controller(ShipmentController::class)->group(function () {
            Route::get('shipments', 'index')->middleware('access:OUT_SHIPMENT,view')->name('shipments.index');
            Route::post('shipments', 'store')->middleware('access:OUT_SHIPMENT,create')->name('shipments.store');
            Route::get('shipments/{shipment}', 'show')->middleware('access:OUT_SHIPMENT,view')->name('shipments.show');
            Route::put('shipments/{shipment}', 'update')->middleware('access:OUT_SHIPMENT,edit')->name('shipments.update');
            Route::post('shipments/{shipment}/confirm', 'confirm')->middleware('access:OUT_SHIPMENT,approve')->name('shipments.confirm');
            Route::post('shipments/{shipment}/cancel', 'cancel')->middleware('access:OUT_SHIPMENT,approve')->name('shipments.cancel');
            Route::post('shipments/{shipment}/go-wave', 'goWave')->middleware('access:OUT_WAVE,create')->name('shipments.go-wave');
        });
        Route::controller(ShipmentDetailController::class)->scopeBindings()->group(function () {
            Route::post('shipments/{shipment}/details', 'store')->middleware('access:OUT_SHIPMENT,create')->name('shipments.details.store');
            Route::put('shipments/{shipment}/details/{detail}', 'update')->middleware('access:OUT_SHIPMENT,edit')->name('shipments.details.update');
            Route::delete('shipments/{shipment}/details/{detail}', 'destroy')->middleware('access:OUT_SHIPMENT,delete')->name('shipments.details.destroy');
        });

        // Wave planning: create = buat wave & Run Wave, edit = alokasi ulang, delete = lepas shipment / batal wave
        Route::controller(WaveController::class)->prefix('waves')->name('waves.')->group(function () {
            Route::get('/', 'index')->middleware('access:OUT_WAVE,view')->name('index');
            Route::post('/', 'store')->middleware('access:OUT_WAVE,create')->name('store');
            Route::post('preview', 'preview')->middleware('access:OUT_WAVE,view')->name('preview');
            Route::get('{wave}', 'show')->middleware('access:OUT_WAVE,view')->name('show');
            Route::post('{wave}/allocate', 'allocate')->middleware('access:OUT_WAVE,create')->name('allocate');
            Route::post('{wave}/reallocate', 'reallocate')->middleware('access:OUT_WAVE,edit')->name('reallocate');
            Route::post('{wave}/shipments', 'addShipments')->middleware('access:OUT_WAVE,create')->name('shipments.add');
            Route::delete('{wave}/shipments/{shipment}', 'removeShipment')->middleware('access:OUT_WAVE,delete')->name('shipments.remove');
            Route::post('{wave}/cancel', 'cancel')->middleware('access:OUT_WAVE,delete')->name('cancel');
        });

        // Picking: desktop + endpoint JSON (dipakai /m/picking). create = pick, approve = short pick / tutup baris
        Route::controller(PickingController::class)->prefix('picking')->name('picking.')->group(function () {
            Route::get('/', 'index')->middleware('access:OUT_PICKING,view')->name('index');
            Route::post('find', 'find')->middleware('access:OUT_PICKING,view')->name('find');
            Route::get('waves/{wave}', 'show')->middleware('access:OUT_PICKING,view')->name('show');
            Route::post('waves/{wave}/start', 'start')->middleware('access:OUT_PICKING,create')->name('start');
            Route::post('waves/{wave}/finish', 'finish')->middleware('access:OUT_PICKING,create')->name('finish');
            Route::post('lines/{line}/pick', 'pick')->middleware('access:OUT_PICKING,create')->name('pick');
        });

        // Dispatch & Surat Jalan (DO): create = buat DO, edit = ubah header / tambah shipment, delete = lepas / batal, approve = kirim
        Route::controller(DispatchController::class)->prefix('dispatch')->name('dispatch.')->group(function () {
            Route::get('/', 'index')->middleware('access:OUT_DISPATCH,view')->name('index');
            Route::post('do', 'store')->middleware('access:OUT_DISPATCH,create')->name('store');
            Route::get('do/{do}', 'show')->middleware('access:OUT_DISPATCH,view')->name('show');
            Route::get('do/{do}/print', 'print')->middleware('access:OUT_DISPATCH,view')->name('print');
            Route::put('do/{do}', 'update')->middleware('access:OUT_DISPATCH,edit')->name('update');
            Route::post('do/{do}/shipments', 'addShipments')->middleware('access:OUT_DISPATCH,edit')->name('shipments.add');
            Route::delete('do/{do}/shipments/{shipment}', 'removeShipment')->middleware('access:OUT_DISPATCH,delete')->name('shipments.remove');
            Route::post('do/{do}/dispatch', 'dispatch')->middleware('access:OUT_DISPATCH,approve')->name('dispatch');
            Route::post('do/{do}/cancel', 'cancel')->middleware('access:OUT_DISPATCH,delete')->name('cancel');
        });
    });

    // ------------------------------------------------------------ Inventory
    Route::prefix('inventory')->name('inventory.')->group(function () use ($report) {
        // Posisi stok: tabel + pivot + grafik + export, klik baris = kartu stok
        $report('stock', 'stock', 'inventory', 'INV_STOCK');

        // Pindah stok / Adjustment / Hold: satu halaman + endpoint JSON. create = jalankan, approve = setujui peringatan (move)
        Route::controller(StockControlController::class)->group(function () {
            Route::get('lookup', 'lookup')->middleware('access:INV_STOCK,view')->name('lookup');
            Route::get('move', 'index')->defaults('mode', 'move')->middleware('access:INV_MOVE,view')->name('move.index');
            Route::post('move', 'move')->middleware('access:INV_MOVE,create')->name('move.store');
            Route::get('adjust', 'index')->defaults('mode', 'adjust')->middleware('access:INV_ADJUST,view')->name('adjust.index');
            Route::post('adjust', 'adjust')->middleware('access:INV_ADJUST,create')->name('adjust.store');
            Route::post('adjust/new', 'adjustNew')->middleware('access:INV_ADJUST,create')->name('adjust.new');
            Route::get('hold', 'index')->defaults('mode', 'hold')->middleware('access:INV_HOLD,view')->name('hold.index');
            Route::post('hold', 'hold')->middleware('access:INV_HOLD,create')->name('hold.store');
            Route::post('hold/release', 'release')->middleware('access:INV_HOLD,create')->name('hold.release');
        });

        // Peta gudang 3D
        Route::controller(WarehouseMapController::class)->prefix('map')->name('map.')->middleware('access:INV_MAP3D,view')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('locations/{location}', 'location')->name('location');
        });
    });

    // ------------------------------------------------------------ Mobile (operator)
    Route::prefix('m')->name('m.')->controller(MobileController::class)->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('receiving', 'receivingIndex')->middleware('access:INB_RECEIVING,view')->name('receiving.index');
        Route::get('receiving/{receipt}', 'receivingShow')->middleware('access:INB_RECEIVING,view')->name('receiving.show');
        Route::get('putaway', 'putaway')->middleware('access:INB_PUTAWAY,view')->name('putaway');
        Route::get('picking', 'picking')->middleware('access:OUT_PICKING,view')->name('picking');
        Route::get('stock', 'stock')->middleware('access:INV_STOCK,view')->name('stock');
    });
});
