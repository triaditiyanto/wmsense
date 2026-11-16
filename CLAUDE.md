# WMSense

Warehouse Management System (WMS) berbasis Laravel, direncanakan menjadi SaaS.
Developer utama mahir PHP native dan baru beralih ke Laravel; jelaskan konsep Laravel bila relevan.
Bahasa komunikasi: Bahasa Indonesia.

## Stack & lingkungan

- Laravel 13 + PHP 8.3, MySQL 8.4 (bawaan Laragon) untuk tahap awal; PostgreSQL dipertimbangkan nanti.
- Redis untuk cache, queue, session, dan atomic lock (`REDIS_CLIENT=predis`). Di mesin dev, Redis dilayani Memurai.
- Development: Laragon di Windows, proyek di `C:\laragon\www\wms`, URL `http://wms.test`, database `wms`.
  PHP CLI tidak ada di PATH shell; pakai `C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe`
  (atau terminal Laragon). Ekstensi `zip` tidak aktif, jadi composer mengunduh lewat git (lebih lambat).
- Production (rencana): Linux (Ubuntu) dengan Nginx + PHP-FPM + Supervisor, atau VM Ubuntu di Hyper-V
  pada server fisik Windows milik developer. Akses publik lewat Cloudflare Tunnel, remote lewat VPN (Tailscale).
- **SaaS multi-tenant (batch 14, buatan sendiri tanpa package)**: satu site = satu database `wms_<code>`. Database PUSAT
  `wms_central` (koneksi `central`, migration di `database/migrations/central`, model `App\Models\Central\*`): tabel `tenant`
  (code, db_name, warehouse_type, status) & `tenant_login` (sesi login: login_at/last_seen_at/logout_at). Login WAJIB
  `username@kodesite` (`LoginController` -> `Tenancy::use($tenant)` = ganti `database.connections.mysql.database` + `DB::purge`
  + prefix cache per tenant); `tenant_id` disimpan di session dan middleware `ResolveTenant` (grup web) memasangnya tiap request
  + heartbeat `last_seen_at` (maks 1x/menit). Database .env (`wms`) = site `demo` (didaftarkan `php artisan portal:install`).
  JANGAN tambah kolom `tenant_id` di tabel WMS; model pusat harus `$connection = 'central'`.
- **Portal** (`/portal`, `PortalController`, view `portal/*`, CSS `public/css/portal.css`, di luar login WMS): landing + statistik,
  registrasi site (`TenantProvisioner`: CREATE DATABASE -> migrate -> `TenantSeeder` (role/modul/akses) -> user admin
  (Superadmin) -> `TenantMasterSeeder` master dummy sesuai `App\Support\WarehouseType` (GENERAL/FMCG/COLD/PHARMA/ECOMMERCE/
  THREEPL, profil tersimpan di `master_warehouse.profile`), ~25 detik), monitoring (`/portal/monitor`, password
  `PORTAL_PASSWORD` di .env: siapa online (< 5 menit), user paling aktif 30 hari, jam online harian/bulanan, login per hari).
- `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id` (pesan validasi di `lang/id/validation.php`).
- Reset data dev: `php artisan migrate:fresh --seed`. User contoh: superadmin, manager, spv, admin, operator
  (password `password`).

## UI

- Blade + Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 + Tom Select 2.3.1, disimpan lokal di `public/vendor/`
  (bukan CDN: jsdelivr diblokir di jaringan developer, dan gudang bisa saja tanpa internet). Tanpa Vite/npm.
  Gaya ERP (struktur ala Odoo) + tampilan ala Apple: biru `#007aff`, sudut membulat, efek glass (blur +
  tembus pandang, makin terang/terangkat saat hover), statusbar dan tab = segmented control. Tema di
  `public/css/wms.css` (variabel `--o-*`, utilitas `.o-glass`). Layout `layouts/app.blade.php`:
  - >= 992px: **sidebar accordion** biru (modul = accordion `data-bs-parent`, `menu_group` = label sub-bagian,
    modul aktif terbuka) + topbar kaca (judul modul > menu, tombol Panduan, lonceng, user).
  - < 992px: topbar jadi **navbar biru**; sidebar (`offcanvas-lg offcanvas-top`) jadi panel menu yang turun dari navbar.
  - < 768px: setiap tabel di `.o-list` otomatis jadi **kartu**. `wms.js` `cardify()` mengisi `td[data-label]` dari
    `<thead>`; sel berlabel pertama = judul kartu (atau beri `class="o-td-title"` sendiri), sel checkbox -> pojok kanan,
    sel tanpa header (aksi) -> bawah. Tabel yang dibuat lewat JS: panggil `WMS.cardify(table)`.
  - Beranda = grid ikon modul. Toast untuk flash.
- **Panduan animasi di setiap modul**: isi di `config/guides.php` (key = kode module detail, 'HOME' = beranda;
  slide = scene + title + text). Layout otomatis memasang `<x-guide>` untuk menu aktif (tombol "Panduan" di topbar).
  Tampil otomatis SETIAP membuka modul sampai user mencentang **"Jangan tampilkan lagi"** (`[data-g-dontshow]`,
  localStorage per user per browser; centang bisa dilepas lagi lewat tombol Panduan). Halaman mobile bisa memakai
  `<x-guide code="M_..."/>` yang sama (contoh `mobile/putaway`). Adegan di `components/guide-scene.blade.php` (nav, list,
  form, hierarchy, matrix, roles, capacity, scan, truck, doc, dock, undo, partners, uom, toggle, print, putaway, pivot,
  chart, excel), CSS `public/css/guide.css`.
- **Laporan** (`app/Reports/*`, `InboundReportController`, view `inbound/reports/index`, JS `public/js/report.js`):
  satu definisi kolom (label, type, sql, default, dim, measure, map) dipakai tabel, pivot/grafik & export. Tab Tabel
  (server, paginasi) / Pivot (diolah di browser dari `dataset`, maks 20.000 baris) / Grafik (Chart.js lokal
  `public/vendor/chartjs`, dari hasil pivot). Export Excel: semua kolom atau kolom terpilih (`?columns[]=`, disimpan
  di localStorage), dan export hasil pivot. Excel ditulis `App\Support\Xlsx\XlsxWriter` (PHP murni: ekstensi `zip`
  TIDAK aktif di Laragon; zip ditulis manual + zlib). Laporan baru: subclass `Report`, daftar di controller,
  route per laporan (nama route sendiri agar bisa jadi menu), module detail + entri `config/guides.php`.
  **Modul baru WAJIB menambah entri di `config/guides.php`.** Naikkan `version` bila isi panduan berubah.
- **Laporan** generik: route dibuat helper `$report(uri, name, key, code)` di routes/web.php -> `ReportController`
  (`REPORTS` map), view `reports/index`. Opsi di subclass `Report`: `routeBase()`, `hasPeriod()` (false = snapshot),
  `searchColumns()` (kotak Cari), `rowKey()` + `rowDetail()` (klik baris -> modal), `section()` (breadcrumb).
- **Cetak label** (`LabelService`, `App\Support\Label\LabelCode`, view `labels/print` = HTML + CSS `@page`, dicetak lewat
  dialog print browser / Simpan PDF): QR (bacon/bacon-qr-code) atau Code 128 (picqer/php-barcode-generator), SVG inline.
  Isi kode = teks polos (nomor LPN / kode lokasi). ATURAN agar bisa di-scan kamera HP (html5-qrcode/zxing-js, membaca
  per baris piksel): barcode SELALU di baris sendiri selebar label (tidak boleh ada QR / blok warna sejajar), lebar modul
  TETAP dalam mm (0,5/0,4/0,33/0,25 - jangan ditarik CSS); bila < 0,25 mm muncul peringatan. Diuji decode zxing-js &
  zxing-cpp dari screenshot label. Jejak cetak: `print_count`, `last_printed_at` di master_lpn & master_location.
- **Peta Gudang 3D** (`WarehouseMapController`, `public/js/warehouse-3d.js`, Three.js r170 lokal `public/vendor/three` via
  importmap): tata letak dihitung dari koordinat lokasi (aisle = baris rak, bay = kolom, level = tingkat, position = slot;
  ukuran dari kelas lokasi), lokasi tanpa aisle = petak lantai per fungsi di depan rak. InstancedMesh, klik = panel isi.
  Lokasi yang ingin tampil sebagai rak WAJIB punya aisle + bay + level.
- **Item Locating** (Master > Item Locating, `MST_LOCATING`; `LocatingRuleController`, `public/js/locating-rules.js`,
  SortableJS lokal `public/vendor/sortable` dengan `forceFallback` agar sama di mouse & sentuh): editor drag & drop aturan
  putaway per Item / Item Class / Item Group per gudang. Logika cocok-area di JS = `LocatingRuleTarget::matches()` (jaga sama).
- **Outbound** (Outbound > Shipment / Wave Planning / Picking / Dispatch; `App\Http\Controllers\Outbound\*`, view
  `resources/views/outbound/*`): daftar dengan centang banyak baris -> tombol aksi massal (Buat Wave / Buat Surat Jalan) mengisi
  hidden `shipment_ids[]` ke modal saat `show.bs.modal`. Modal shipment: alamat kirim diisi dari `addresses` opsi Tom Select
  customer (`lookup.customers`); saat ubah, `data-record` membawa `customer_addresses`.
- Pola input = **modal**. Tombol pembuka: `data-bs-toggle="modal" data-bs-target="#modalX" data-action="url"
  data-method="PUT" data-title="..." data-record="{{ json_encode([...]) }}"`. Form di modal: `<form data-ajax>`
  (+ hidden `_method`, + `<div class="alert alert-danger form-alert d-none">`). Ditangani `public/js/wms.js`:
  isi field dari data-record, submit via fetch, error 422 tampil per field, sukses -> reload/redirect.
  Event `wms:fill` di modal untuk logika khusus halaman (mis. Tom Select).
- Dropdown master di dokumen transaksi = **remote select** (otomatis oleh wms.js):
  `<select name="supplier_id" data-remote="{{ route('lookup.suppliers') }}" data-remote-depends="owner_id">`.
  Saat ubah, kirim teks pilihan di record sebagai `<name>_text` (mis. `supplier_id_text`).
  Endpoint `lookup.*` (LookupController) mengembalikan `[{id, text, ...}]`: items, suppliers, customers
  (customers ikut membawa `addresses` untuk outbound).
- Field bersyarat: `data-show-when="receipt_type=PURCHASE,OTHER"` (tampil bila field lain bernilai tsb).
- JANGAN pakai `@json([...])` di atribut HTML: Blade memecah argumennya per koma. Pakai `{{ json_encode(...) }}`.
- Controller untuk form modal mengembalikan `$this->ok('pesan', ?redirect)` (JSON + flash `success`).
  Aturan bisnis dilempar sebagai `BusinessException` -> otomatis jadi JSON 422 / flash `error`.
- Pola halaman (pakai class yang sudah ada, jangan bikin gaya baru):
  - List: `.o-control-panel` (tombol Baru + `.o-breadcrumb` + `partials.searchbar` dengan filter facet +
    `partials.pager`) lalu `.o-list` table; baris `tr[data-href]` = buka dokumen, baris dengan data-bs-toggle = modal.
  - Dokumen: control panel (breadcrumb) -> `.o-form-header` (tombol aksi kiri, `.o-statusbar` kanan) ->
    `.o-sheet` (`.o-title`, `.o-group` berisi `.o-field` label/nilai, `.o-notebook` tab detail + `.o-add-line`).
  - Modal: `<form class="modal-content o-input">`, field `.o-field` (label kiri), wajib = `label.o-required`.
  - Badge status: `badge rounded-pill bg-{c}-subtle text-{c}-emphasis` (c dari enum `color()`).
- Dokumen transaksi: halaman daftar (filter + modal buat header) -> halaman dokumen
  (sheet header + modal ubah header, tab detail + modal tambah/ubah baris, statusbar).
- Konfirmasi bisnis: service melempar `ConfirmationRequiredException($warnings, $canConfirm)` -> HTTP 409
  `{confirm:true, warnings, can_confirm}`. wms.js (form data-ajax) otomatis tanya user lalu kirim ulang `confirm=1`.

## Mobile (operator)

- Prefix `/m` (route `m.*`, `MobileController`), layout `layouts/mobile.blade.php` (app bar, kartu kaca, tombol
  aksi menempel bawah, toast `MToast()`). Login dari user-agent HP/handheld otomatis ke `m.home`.
- Endpoint JSON dipakai bersama desktop & mobile (mis. `inbound.receiving.*`), jadi aturan bisnis hanya di service.
- Scan: `public/js/scanner.js` -> `WMSScanner.open({title, onResult})` (kamera, library html5-qrcode di
  `public/vendor/html5-qrcode`). Scanner genggam (keyboard wedge) cukup input + Enter.
  Kamera HP HANYA jalan di HTTPS (atau localhost): dev pakai Cloudflare Tunnel (`trustProxies('*')` sudah aktif).
- Helper JS: `WMS.api(url, {method, body, params})` -> `{ok, status, data}`, `WMS.uuid()` untuk idempotency_key
  (key sama dipakai ulang sampai transaksi sukses, lalu dibuat baru).
- Alur operator = WIZARD langkah demi langkah (contoh: `mobile/receiving/show.blade.php` + `public/js/m-receiving.js`):
  stepper (ketuk langkah lewat untuk ubah) -> chip pilihan -> SATU `.m-panel[data-step]` tampil -> bar aksi bawah
  (Kembali + tombol utama per langkah). Scan sukses langsung maju langkah. Setiap panel punya `.m-coach`
  (ikon animasi + instruksi + "Lihat contoh") dan `[data-note]` untuk pesan kontekstual (error/peringatan).
  Langkah terakhir = ringkasan dengan "Ubah", lalu layar selesai dengan aksi lanjutan.
- Panduan animasi "sebelum mulai": `public/js/m-guide.js` -> `MGuide.create(el, {key}).autoOpen()/open(step)`,
  markup slide di partial (contoh `mobile/receiving/_guide.blade.php`, `.g-slide[data-step]`) atau `<x-guide>`.
  Tampil otomatis sampai dicentang "Jangan tampilkan lagi" (localStorage `mGuide:<modul>:v<n>:<userId>`).
  Gaya wizard & panduan di `public/css/mobile-flow.css` (dimuat layout mobile). Aturan animasi: gaya dasar = keadaan
  akhir, @keyframes hanya `from`, dan `prefers-reduced-motion` mematikan semua animasi.
- Modul mobile berikutnya (putaway, picking) WAJIB memakai pola wizard + MGuide yang sama.

## Kapasitas lokasi & notifikasi

- Kapasitas efektif `Location::capacity()`: kolom lokasi (`max_lpn`, `max_weight_kg`, `max_volume_m3`), NULL =
  warisan kelas lokasi, keduanya NULL = tidak dibatasi.
- `LocationCapacityService::statusFor($locations, $addLpn)` -> per lokasi: used_lpn (LPN berbeda berisi stok),
  berat & volume (qty_on_hand + qty_incoming x berat/dimensi item), percent, empty, full (bila ditambah $addLpn),
  label. Satu query agregat; WAJIB dipakai untuk locating (putaway) & replenishment.
- Receiving: `ReceivingService::dockStatus($header, $currentId)` -> saran dock untuk pallet BARU: dock saat ini
  selama muat -> dock paling kosong -> `all_full`. Menaruh LPN baru di dock penuh = peringatan 409 (hanya SPV).
  Menambah isi LPN yang sudah di dock tsb tidak butuh slot. Klien hanya memindah dock saat pallet baru.
- Notifikasi = Laravel database notification (tabel `notifications`, class di `app/Notifications`), lonceng di navbar
  desktop (`NotificationController`, polling 60 dtk di wms.js). `DockFullNotification` -> user aktif role
  level <= 3 (Admin ke atas), dibatasi 1x / gudang / 10 menit via `Cache::add`.

## Auth & hak akses

- Login pakai `username` (LoginController buatan sendiri, rate limit 5x/menit). Tabel `users` + kolom
  `username`, `role_id`, `is_active`, `last_login_at`.
- `master_role` berjenjang lewat `level` (0 Superadmin, 1 Manager, 2 SPV, 3 Admin, 4 Operator; kecil = lebih tinggi).
  User hanya bisa mengelola user/role dengan level lebih besar dari dirinya (`User::canManage`, `canManageRole`,
  scope `Role::manageableBy`). `is_superadmin` = bypass semua hak akses.
- Hak akses = `master_module` (header) -> `master_module_detail` (detail, `code` mis. `INB_RECEIPT`) x aksi
  (`AccessAction`: view/create/edit/delete/approve) di `master_role_access`. Tidak bisa memberi akses yang
  tidak dimiliki sendiri.
- Cek akses: route `->middleware('access:INB_RECEIPT,create')`, Blade `@can('access', ['INB_RECEIPT','create'])`,
  kode `$user->hasAccess('INB_RECEIPT', 'create')`. Fitur baru = tambah baris di `ModuleSeeder`
  (route_name null = belum ada halaman) lalu `php artisan db:seed --class=ModuleSeeder`.
- Mapping aksi receipt: header/detail tambah = create, ubah = edit, hapus detail = delete, confirm/cancel = approve.

## Fitur inti

- **Inbound**: receipt entry (ASN) oleh admin → receiving oleh operator (scan qty, lot, expired, kondisi;
  selisih dicatat sebagai discrepancy) → stok di lokasi staging/dock → locating membuat putaway task →
  operator scan lokasi tujuan → stok available.
- **Outbound** (sudah ada, batch 13): shipment entry → wave (Go Wave 1 shipment / Wave Planning banyak) → Run Wave =
  alokasi FIFO/FEFO/LIFO/LEFO (qty_allocated naik) → picking ke staging (PICK_OUT/PICK_IN, tetap allocated) → DO / Surat Jalan
  → dispatch (SHIP). Packing/checking belum ada.
- **Setup wizard per warehouse/tenant**: menanyakan profil gudang (jenis bisnis, karakteristik produk,
  tipe penyimpanan per zona, unit handling inbound/outbound, layout, kebijakan mixing, minimum shelf life,
  prioritas kecepatan vs utilisasi ruang). Jawaban disimpan di `master_warehouse.profile` (JSON).
- **Recommendation engine**: memetakan profil ke rule set awal secara deklaratif (data/config, bukan if-else).
  Contoh: ada expired → FEFO + no mix lot; drive-in → LIFO per lane; e-commerce → pick face + replenishment;
  3PL → filter zona per owner; barang berat → level bawah.
- **Rule engine locating & allocating**: pipeline langkah `filter` lalu `scorer`/`sorter` berbobot.
  Tiap strategi = satu class kecil yang mengimplementasikan interface yang sama, didaftarkan di
  `StrategyRegistry` (key string → class). Rule tersimpan di DB: `master_rule_set`, `master_rule_step` (strategy key,
  params JSON, weight, urutan), `master_rule_assignment` (scope). Resolusi scope: Item > Item Class > Item Group > Owner >
  Zona > Warehouse (paling spesifik menang); filter lokasi bisa memakai Location Class / Location Type. Rule berversi (draft/active), ada mode simulasi dan log penjelasan
  keputusan.

## Penamaan tabel

- Nama tabel **singular**, snake_case, dengan prefix:
  - `master_` untuk data master/setup: `master_item`, `master_location`, `master_rule_set`, ...
  - `o_` untuk data operasional/transaksi: `o_inventory`, `o_inventory_movement`, ...
- Dokumen transaksi memakai pasangan **header/detail**: `o_receipt_header` + `o_receipt_detail`,
  `o_order_header` + `o_order_detail`. FK di detail: `receipt_header_id`, `order_header_id`.
  Model: `ReceiptHeader`, `ReceiptDetail` (relasi `details()` / `header()`).
- Tabel bawaan Laravel (`users`, `cache`, `jobs`, `sessions`, `migrations`, ...) tidak di-rename.
- Kolom FK tetap `<entitas>_id` tanpa prefix (`item_id`, `warehouse_id`), jadi karena nama tabel tidak
  mengikuti tebakan Laravel, selalu tulis eksplisit: `constrained('master_item')` di migration dan
  `protected $table = 'master_item';` di model.

## Hierarki master data

- **Item**: `master_item_group` → `master_item_class` (`item_group_id`) → `master_item` (`item_class_id`, wajib).
  Model: `ItemGroup` → `ItemClass` → `Item`. Group tidak disimpan di item; ambil lewat class
  (`Item::with('itemClass.itemGroup')`, atau `$group->items()` = hasManyThrough).
- **Location**: `master_location_type` → `master_location_class` (`location_type_id`) → `master_location`
  (`location_class_id`, wajib). Model: `LocationType` → `LocationClass` → `Location`.
  - Type = fungsi lokasi. Kode & nama bebas diatur user, tapi perilaku sistem dibaca dari kolom
    `location_function` (enum `LocationFunction`: RECEIVING, STORAGE, PICK_FACE, STAGING_OUT, SHIPPING, QC,
    QUARANTINE, VIRTUAL). Jangan menulis logika berdasarkan `code` tipe.
  - Class = template fisik: `storage_type` (selective_rack, drive_in, floor, ...), dimensi, kapasitas
    (`max_weight_kg`, `max_volume_m3`, `max_lpn`), aturan mixing. Lokasi beda ukuran/aturan = class beda.
  - Location = bin fisik: zona, koordinat (aisle/bay/level/position), `pick_sequence`, status, override suhu.
    `$location->locationFunction()` butuh eager load `locationClass.locationType`.
- Zona (`master_zone`) terpisah dari hierarki: area fisik/suhu di dalam warehouse.
- **Pallet** (`master_pallet`, model `Pallet`) = jenis/spesifikasi pallet (material, dimensi, berat kosong,
  beban & tinggi tumpuk maks), BUKAN aset pallet satu per satu.
- **Mitra**: Owner (pemilik barang / klien 3PL), Supplier (pengirim inbound), Customer + alamat kirim
  (penerima outbound, pengirim retur). Supplier & customer bisa khusus satu owner atau bersama.
- **LPN** (`master_lpn`, model `Lpn`) = lokasi bergerak: label barcode di pallet/karton/tote, dipakai dari
  inbound sampai outbound. Kolom: `lpn_no` (unik, huruf besar), `warehouse_id`, `lpn_type` (`LpnType`),
  `pallet_id` (hanya bila PALLET), `status` (`LpnStatus`: EMPTY <-> ACTIVE -> SHIPPED, EMPTY -> VOID),
  `location_id` (posisi terakhir), `registered_via` (MASTER | RECEIVING).
  - Registrasi lewat master: `LpnService::generate()` (nomor otomatis `LPN-WH1-26-000001`) atau `register()`
    (nomor manual / SSCC). Saat receiving: `LpnService::findOrRegister()` (auto-daftar, aman dari scan bersamaan).
  - Stok di dalam LPN: `o_inventory.lpn_id`. Invarian (dijaga service): `inventory.location_id` = `lpn.location_id`;
    memindah LPN = memindah semua stok di dalamnya. Status EMPTY/ACTIVE diubah oleh InventoryService.

## Konvensi wajib

- Semua qty disimpan dalam base UOM item, tipe `decimal(18,4)` (cast `decimal:4` → string di PHP;
  hitung qty dengan bcmath/BigDecimal, jangan float).
- `o_inventory` hanya diubah lewat service class di dalam `DB::transaction` + `lockForUpdate()`,
  dengan urutan lock konsisten (`orderBy('id')`) untuk mencegah deadlock.
- Setiap perubahan stok wajib menulis `o_inventory_movement` (append-only; model melempar
  `ImmutableRecordException` saat update/delete). Koreksi lewat movement ADJUST_IN / ADJUST_OUT.
  Catatan: guard model tidak berlaku untuk query builder (`InventoryMovement::query()->update()`) — jangan dipakai.
- Satu movement = satu baris inventory. Transaksi pindah stok menulis pasangan `*_OUT` + `*_IN`
  (PUTAWAY, MOVE, REPLENISH, PICK, STATUS). Allocation/hold juga ditulis (ALLOCATE/DEALLOCATE, HOLD/RELEASE).
- Transaksi dari scanner membawa `idempotency_key`; unique `(idempotency_key, seq)` di
  `o_inventory_movement` mencegah pencatatan dobel (seq = urutan movement dalam satu transaksi).
- Jangan memanggil API eksternal di dalam DB transaction. Pekerjaan berat (wave allocation, laporan,
  label) lewat queue.
- Status dokumen dikelola sebagai state machine (tidak boleh mundur sembarangan).
- `qty_available = qty_on_hand - qty_allocated - qty_hold` — generated column (`storedAs`) di DB, read-only.
  `qty_incoming` = reservasi kapasitas lokasi dari putaway task yang belum selesai.
- `o_inventory.stock_key` = sha256(item|owner|lot|exp|lpn_id|status), diisi otomatis oleh model
  (`Inventory::makeStockKey()` untuk lookup); unique bersama `location_id`.
- Setiap item & inventory wajib punya `owner_id` (gudang non-3PL memakai satu owner default).
- Master data tidak memakai soft delete; nonaktifkan lewat `is_active` / status. FK ke master `restrictOnDelete`.
- `Model::shouldBeStrict()` aktif di luar production (lazy loading & mass-assignment liar jadi error).

## Status pengerjaan

Selesai (batch 1, sudah diuji 2026-11-08: `migrate:fresh`, `rollback`, smoke test tinker):
- Migration: `master_warehouse`, `master_zone`, `master_location_type`, `master_location_class`,
  `master_location`, `master_owner`, `master_item_group`, `master_item_class`, `master_uom`, `master_item`,
  `master_item_uom`, `o_inventory`, `o_inventory_movement`.
- Model untuk semua tabel di atas, enum di `app/Enums`
  (LocationFunction, LocationStatus, InventoryStatus, MovementType, RotationRule, TemperatureClass).

Selesai (batch 2, diuji end-to-end via HTTP 2026-11-08):
- Login/logout, ganti password, dashboard, sidebar dinamis.
- Master User & Role & matriks hak akses (`master_role`, `master_module`, `master_module_detail`,
  `master_role_access`).
- Inbound Receipt (ASN): `o_receipt_header` + `o_receipt_detail`, nomor otomatis `o_document_sequence`
  (RCV-WH1-2611-0001 via `DocumentNumberService`), `ReceiptService`, state machine `ReceiptStatus`
  (DRAFT -> OPEN -> RECEIVING -> RECEIVED -> CLOSED, cancel dari DRAFT/OPEN). Header & detail hanya bisa diubah saat DRAFT.
- Seeder: role, module, hak akses default, user contoh, data master demo (WH1, lokasi, owner, item),
  2 receipt demo (DRAFT & OPEN) via `DemoReceiptSeeder`.
- Tampilan gaya ERP/Odoo + Apple glass biru (batch 3).

Selesai (batch 4, diuji end-to-end via HTTP 2026-11-08):
- Semua halaman master (menu Master Data, dropdown per `menu_group`: Gudang / Produk / Pengguna):
  Gudang (header) + Zona (detail), Tipe Lokasi, Kelas Lokasi, Lokasi (+ generate massal pola
  ZONA-AISLE-BAY-LEVEL via `LocationService`), Jenis Pallet, LPN (generate / registrasi manual / ubah / void),
  Owner, Item Group, Item Class, UOM, Item (header) + UOM & konversi (detail, aturan di `ItemService`).
- Tabel baru `master_pallet`, `master_lpn`; `o_inventory.lpn` & `o_inventory_movement.lpn` diganti `lpn_id` (FK).
- Blade component di `resources/views/components`: `<x-modal-form>`, `<x-field>`, `<x-active-switch>`,
  `<x-active-badge>`, `<x-new-button>`. Atribut `data-lock-on-edit` (readonly saat ubah) di wms.js.
- Route master sederhana dibuat dengan helper `$crud(uri, controller, kode_akses, param)` di routes/web.php.
- Kode gudang tidak bisa diubah setelah dibuat (dipakai di nomor dokumen & LPN).
- Catatan: selama belum ada data production, migration lama masih diedit langsung + `migrate:fresh --seed`.
  Setelah ada data nyata, perubahan skema WAJIB lewat migration baru.

Selesai (batch 5, diuji end-to-end via HTTP 2026-11-08):
- Master Supplier (`master_supplier`) & Customer (`master_customer` header + `master_customer_address` detail
  alamat kirim, satu default per customer). Grup menu **Mitra**: Owner, Supplier, Customer.
- Supplier/customer punya `owner_id` nullable: null = dipakai bersama semua owner, terisi = khusus owner (3PL).
  Scope `forOwner($ownerId)` di model; validasi dokumen wajib cek kepemilikan ini.
- Receipt: `supplier_name` (teks) diganti `supplier_id` + `customer_id`. PURCHASE wajib supplier, RETURN wajib
  customer (`ReceiptType::usesCustomer()`), yang tidak sesuai tipe disimpan null. `ReceiptHeader::senderName()`.

Selesai (batch 6, diuji end-to-end via HTTP 2026-11-08; kamera belum diuji di perangkat nyata):
- `InventoryService::changeOnHand(StockAttributes, delta, MovementType, MovementContext)`: satu-satunya pintu ubah
  o_inventory (lock baris, buat baris via savepoint bila belum ada, tolak stok minus / di bawah allocated+hold,
  tulis ledger). Value object di `app/Services/Inventory/`.
- Receiving (`ReceivingService`, `Inbound\ReceivingController`): tabel `o_receipt_receiving` (riwayat terima, unik
  `idempotency_key`, batal = `cancelled_at` + movement RECEIVE_CANCEL), `o_receipt_discrepancy` (SHORT/OVER/DAMAGED
  saat Selesai Terima), `o_receipt_detail.qty_damaged_base`.
  Aturan: dok OPEN/RECEIVING; lokasi harus fungsi RECEIVING & ACTIVE; item harus ada di dokumen; lot/expired wajib
  sesuai item; barang kedaluwarsa ditolak; lebih ASN / sisa umur < minimal = 409, hanya `INB_RECEIVING:approve`
  yang boleh konfirmasi; LPN kosong = generate baru, nomor belum terdaftar = `findOrRegister`; LPN aktif di lokasi
  lain / berisi owner lain ditolak. Kondisi DAMAGED -> stok status DAMAGED. Pertama terima: OPEN -> RECEIVING.
  Hak akses: terima = create, batal baris = delete, Selesai Terima & konfirmasi peringatan = approve.
  Barcode scan: barcode kemasan (master_item_uom) -> UOM kemasan; barcode item / SKU -> UOM default inbound.
- Desktop: Inbound > Receiving (antrian + halaman kerja, modal Terima dengan input scan USB).
  Mobile: `/m` menu, `/m/receiving` (daftar + scan dokumen), `/m/receiving/{id}` (dock -> LPN -> item -> Terima).

Selesai (batch 7, diuji end-to-end via HTTP 2026-11-08):
- Kapasitas per lokasi (migration baru `add_capacity_to_master_location`, dijalankan dengan `migrate` biasa),
  kolom Kapasitas di master Lokasi, saran dock otomatis + tolak dock penuh, tombol "Beri tahu Admin",
  notifikasi database + lonceng navbar. Demo: kelas DOCK max 4 LPN.

Selesai (batch 8, diuji UI via Chrome headless/CDP 2026-11-08):
- Penerimaan mobile jadi wizard 5 langkah (Dock -> LPN -> Item -> Detail -> Cek) + layar selesai, coach per langkah,
  cek lokal (qty > sisa dokumen, expired lewat/< 30 hari, kondisi Rusak), pilih item dari daftar bila barcode rusak,
  panduan animasi 7 slide (termasuk checklist "Sebelum mulai").
- Fix: `Env::disablePutenv()` di `bootstrap/app.php`. Apache + PHP Thread Safe (Laragon) membuat request paralel
  acak gagal 500 "No application encryption key" (putenv global per proses). Terbukti 4/60 gagal -> 0/180.

Selesai (batch 9, diuji HTTP + Chrome headless 2026-11-08):
- Inbound > **Intransit In** (`INB_INTRANSIT`, `IntransitController`): baris terima yang barangnya masih di lokasi
  fungsi RECEIVING (belum putaway), urut terlama, KPI + chip per dock + tanda > 4 jam (`STALE_HOURS`).
  Batal terima (hak `delete`, alasan wajib, banyak baris sekaligus) -> `ReceivingService::cancelLines()` ->
  `cancelLine()`: stok dock -RECEIVE_CANCEL, qty diterima dikembalikan, LPN kosong -> EMPTY. Dokumen RECEIVED dibuka
  ulang ke RECEIVING (transisi baru di `ReceiptStatus`) + selisih lama dihapus (dihitung ulang saat Selesai Terima).
  Default: SPV/Manager boleh batal, Admin hanya lihat, Operator tidak ada akses.
  Demo: receipt `PO-2611-0471` sudah RECEIVED, 2 pallet di RCV-02 (satu dimundurkan 6 jam).
- Layout desktop: sidebar accordion, navbar + tabel jadi kartu di HP, panduan animasi untuk semua 19 menu.

Selesai (batch 10, diuji HTTP + tinker + Chrome headless 2026-11-08):
- Ceklis "Jangan tampilkan lagi" di semua panduan.
- **Putaway** (`INB_PUTAWAY`; desktop Inbound > Putaway + mobile `/m/putaway`, endpoint JSON sama di
  `PutawayController`): tabel `o_task_header` / `o_task_detail` (task_type PUTAWAY, status IN_PROGRESS -> DONE /
  CANCELLED, nomor `PTW-...`), kolom `o_receipt_receiving.putaway_task_id/putaway_at`.
  `LocatingService`: lokasi STORAGE aktif, suhu sama, kapasitas (+ slot yang dipesan task IN_PROGRESS lain),
  aturan campur item/lot; urut gabung item sama -> kosong -> masih muat, level, pick_sequence.
  `PutawayService::complete`: PUTAWAY_OUT/IN (received_at ikut), LPN pindah lokasi, baris terima ditandai putaway_at
  (hilang dari Intransit, `cancelLine` menolak), receipt RECEIVED yang semua barisnya tersimpan -> CLOSED (juga dicek
  saat Selesai Terima). Lokasi penuh/campur/suhu beda = 409, setuju butuh `INB_PUTAWAY:approve`.
- **Laporan** Inbound > Laporan: **Inbound Detail** (`INB_RPT_DETAIL`, per baris terima, periode = waktu terima) dan
  **Inbound Summary** (`INB_RPT_SUMMARY`, per receipt: fill rate, selisih, tepat waktu, lama terima, % tersimpan;
  periode = rencana datang). Tabel + Pivot + Grafik + Export Excel (semua / pilih kolom / pivot).

Selesai (batch 11, diuji HTTP + Chrome headless + decode zxing 2026-11-08):
- Master > **Cetak Label** (`MST_LABEL`; tab LPN / Lokasi, centang / semua sesuai filter, QR / Barcode / Keduanya,
  ukuran pallet tag 100x150, 100x50, label rak 100x30, 50x25, stiker A4 3x8, salinan, isi LPN, warna per level,
  pratinjau, "LPN baru & cetak" butuh MST_LPN:create). Receipt > **Cetak Label LPN** (`ReceiptLabelController`): siapkan
  LPN baru (notes "Untuk {receipt_no}", butuh INB_RECEIVING:create atau MST_LPN:create), cetak ulang yang disiapkan,
  atau pallet tag LPN yang sudah diterima (isi + no. dokumen).
- Inventory > **Inventory** (`INV_STOCK`, `InventoryReport`: posisi stok, cari, filter zona/fungsi/owner/status/expired,
  KPI, pivot/grafik/export, klik baris = kartu stok 30 movement) dan **Peta Gudang 3D** (`INV_MAP3D`; warna kondisi stok /
  utilisasi / owner / item / umur, filter level, cari + pin, 3D/Denah/Depan, `?focus=KODE`). Operator: lihat keduanya.
- Demo: aisle A-02 & A-03 (6 bay x 4 level), PF-01..05 ber-aisle 'PF', `DemoStockSeeder` (saldo awal ADJUST_IN alasan
  OPENING: 36 pallet, 1 kedaluwarsa, beberapa <= 30 hari, 1 rusak, stok lepas di pick face).

Selesai (batch 12, diuji HTTP + Chrome headless dengan drag mouse sungguhan 2026-11-16):
- **Aturan locating**: `master_locating_rule` (warehouse, scope_type ITEM_GROUP/ITEM_CLASS/ITEM + scope_id, level_preference
  LOW/HIGH, prefer_consolidate, strict, is_active) + `master_locating_rule_target` (seq, ZONE/AISLE/LOCATION_CLASS/LOCATION,
  level_min/max). `LocatingRuleService::resolve()` = paling spesifik menang (Item > Class > Group), `forContents()` = item
  dengan qty terbanyak di LPN, `draft()` untuk simulasi tanpa simpan. `LocatingService::suggest()` mengurutkan: prioritas
  area -> konsolidasi -> level (arah dari aturan) -> pick_sequence; strict = lokasi di luar area dibuang dan `check()` memberi
  peringatan 409 (butuh INB_PUTAWAY:approve). Alasan saran memuat "Aturan prioritas n".
- Editor: pohon barang (badge aturan sendiri / ikut induk), daftar prioritas drag & drop + level per area, denah
  aisle x bay x level berwarna prioritas (klik aisle/lokasi = tambah), simulasi saran live, `DemoLocatingSeeder` (3 aturan).
- `start-wms.bat` / `stop-wms.bat`: jalankan tanpa jendela Laragon (MySQL + php artisan serve :8000).

Selesai (batch 13, diuji HTTP + tinker + Chrome headless 2026-11-16) - **Outbound**:
- Tabel: `o_shipment_header/detail` (SHP-..., `allocation_rule` per shipment, `priority`, snapshot `ship_to_*`), `o_wave_header`
  (WAV-..., `wave_type` GO/PLAN, `allocation_rule`, `staging_location_id`), `o_allocation` (= baris pick list: inventory
  sumber, qty_allocated/picked, status OPEN/PICKED/SHORT/CANCELLED, `rule`), `o_delivery_order_header` (DO-..., kendaraan/sopir;
  detailnya = `o_shipment_header.delivery_order_id`). Status: `ShipmentStatus` (DRAFT→OPEN→WAVED→ALLOCATED→PICKING→PICKED→
  DISPATCHED), `WaveStatus`, `AllocationStatus`, `DeliveryOrderStatus`. `RotationRule` + LEFO.
- `InventoryService::change(attr, onHandDelta, allocatedDelta, type, ctx)` = pintu umum (changeOnHand memanggilnya):
  ALLOCATE/DEALLOCATE (allocated), PICK_OUT (-on_hand -allocated), PICK_IN di staging (+on_hand +allocated: tetap tereservasi),
  SHIP (-on_hand -allocated). Stok di STAGING_OUT tidak pernah jadi kandidat alokasi (fungsi lokasi bukan STORAGE/PICK_FACE).
- `AllocationService`: aturan efektif = wave > shipment > item (`ShipmentHeader::ruleFor`). Kandidat: AVAILABLE, qty_available>0,
  lokasi ACTIVE STORAGE/PICK_FACE, item+owner sama, belum expired, lot sesuai bila baris membatasi. Urut FIFO/LIFO = received_at,
  FEFO/LEFO = expiry (null paling akhir) lalu received_at, seri = pick_sequence. Boleh pecah lokasi; sisa = "kurang stok".
  `release()` melepas alokasi OPEN. `preview()` = simulasi urutan (tombol "Simulasi urutan" di wave).
- `WaveService`: create (Wave Planning, banyak shipment OPEN satu gudang) / goWave (1 shipment, langsung alokasi) / allocate
  (Run Wave, idempoten: hanya isi sisa) / reallocate (lepas OPEN lalu alokasi lagi, bisa ganti aturan) / add-remove shipment / cancel.
- `PickingService`: queue, payload (baris urut pick_sequence + barcodes item/LPN untuk verifikasi di klien), start (staging
  STAGING_OUT), pick (idempotency_key; short pick = 409 butuh `OUT_PICKING:approve`, sisa DEALLOCATE; LPN kosong -> EMPTY),
  finish (baris belum dipick -> SHORT, butuh approve; shipment tanpa hasil pick kembali OPEN + wave_id null).
  Desktop Outbound > Picking (modal per wave) + mobile `/m/picking` (wizard Wave -> Lokasi -> Barang, `public/js/m-picking.js`).
- `DispatchService`: DO dari shipment PICKED (multi-drop boleh), ubah header/tambah/keluarkan selama DRAFT, `dispatch()` =
  SHIP dari staging per baris alokasi + shipment DISPATCHED (hak approve), cetak `outbound/dispatch/print` (A4, draft = watermark).
- Hak akses: OUT_SHIPMENT (Admin VCEDA), OUT_WAVE (create = buat/Run/Go Wave, edit = alokasi ulang, delete = lepas/batal; Admin VCE),
  OUT_PICKING (Operator VC), OUT_DISPATCH (Admin VCED, kirim = approve SPV). Lokasi demo `STG-OUT-01/02` (tipe STO = STAGING_OUT).
  `DemoShipmentSeeder`: wave FEFO 2 shipment teralokasi, 1 shipment OPEN, 1 DRAFT.

Selesai (batch 14, diuji HTTP 2026-11-16): **Laporan Outbound Detail/Summary** (`OUT_RPT_DETAIL/SUMMARY`, periode = tanggal
alokasi / rencana kirim), urutan modul Inbound > Inventory > Outbound, DO bisa dibuat kosong (manual) lalu ditambah shipment,
**multi-tenant + portal** (lihat bagian Stack), `start-wms.bat` sekali klik (composer install bila vendor kosong, .env + APP_KEY,
buat DB / import backup terbaru `database/backup/*.sql` / migrate, `portal:install`, serve) + `backup-wms.bat` (mysqldump semua
database `wms*`).

Selesai (batch 15, diuji tinker + HTTP 2026-11-16) - **Kontrol stok Inventory**:
- `StockControlService` (+ `Inventory\StockControlController`, view `inventory/control.blade.php` satu halaman mode move|adjust|hold, tab Stok / Riwayat):
  **Pindah Stok** (`INV_MOVE`; baris ber-LPN = pindah LPN utuh, stok lepas = qty sebagian; tolak stok teralokasi/hold; sumber RECEIVING/STAGING_OUT/SHIPPING
  tidak boleh; tujuan STORAGE/PICK_FACE/QC/QUARANTINE aktif; peringatan suhu/penuh/campur butuh `INV_MOVE:approve`), **Adjustment** (`INV_ADJUST`; qty hasil hitung -> ADJUST_IN/OUT,
  alasan wajib, + "Stok temuan" baris baru; LPN habis -> EMPTY), **Hold & Release** (`INV_HOLD`; `qty_hold`, tidak ikut alokasi). Alasan di `reason_code` + `notes` movement.
- `InventoryService::change(..., holdDelta)` parameter baru (HOLD/RELEASE). `INV_MOVEMENT` (kartu stok kosong) dihapus; kartu stok tetap di klik baris laporan Inventory.
- Mobile **Cek Stok** `/m/stock` (scan/ketik lokasi, LPN, SKU, lot -> `inventory/lookup` JSON). Operator: hanya Cek Stok.
- `php artisan portal:upgrade` (dipanggil start-wms.bat): migrate + ModuleSeeder untuk SEMUA site; hak akses default hanya utk modul baru (`RoleAccessSeeder::$only`).
  Wajib dijalankan setelah menambah modul / migration baru agar site yang sudah ada ikut.

**Deploy server** (`setup-server.bat [domain] [https]` -> `setup-server.ps1`, Run as administrator, aman diulang): pasang Laragon bila belum ada,
unduh Redis (tporadowski, service `Redis`) & composer.phar, MySQL jadi service `WMSMySQL`, user MySQL `wms_app` (hak ALL hanya `wms%`) ditulis ke `.env`,
`.env` produksi (APP_ENV/DEBUG/URL, PORTAL_PASSWORD acak), vhost `C:\laragon\etcpache2\sites-enabled -wms-server.conf` (nama `0-` agar jadi host default)
+ service `WMSApache`, firewall 80/443 (3306/6379 tertutup), task `WMSense Backup` 01:00 (`backup-auto.bat`, simpan 14 file), lalu `start-wms.bat prepare`
(langkah 1-5 saja, tanpa serve). Server TIDAK boleh menjalankan Laragon GUI bersamaan (port 80). Belum diuji di mesin kosong.

Berikutnya:
1. Mobile pindah stok (scan LPN -> scan lokasi) + stock opname terjadwal.
2. Replenishment pick face; packing/checking sebelum DO.
3. Rule engine lanjutan (Filter/Scorer berbobot, StrategyRegistry, scope Owner/Zona) di atas aturan Item Locating & alokasi.
4. Setup wizard + recommendation engine.
