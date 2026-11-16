<?php

/*
|--------------------------------------------------------------------------
| Panduan animasi per modul (desktop)
|--------------------------------------------------------------------------
| Key = kode module detail (master_module_detail.code) atau 'HOME' untuk beranda.
| Layout app otomatis memasang panduan untuk menu yang sedang dibuka: tombol "Panduan" di topbar,
| dan tampil otomatis SEKALI per user per browser (localStorage). Naikkan 'version' bila isi berubah
| supaya semua user melihatnya lagi.
|
| Slide: scene (animasi, lihat components/guide-scene.blade.php), opts (opsi scene), badge, title, text.
| Teks boleh **tebal** (inline markdown).
|
| Scene: nav, list, form, hierarchy, matrix, roles, capacity, scan, truck, doc, dock, undo, partners,
|        uom, toggle, print
*/

return [
    'HOME' => ['title' => 'Selamat datang', 'version' => 1, 'slides' => [
        ['scene' => 'nav', 'title' => 'Menu ada di samping kiri',
            'text' => 'Klik nama modul (**Master Data**, **Inbound**, ...) untuk membuka sub-menunya. Di HP, menu dibuka lewat tombol **☰** di kiri atas.'],
        ['scene' => 'list', 'opts' => ['q' => 'RCV-01'], 'title' => 'Cari & filter di setiap daftar',
            'text' => 'Ketik di kotak cari, atau buka ikon **filter** untuk menyaring. Di HP, tabel otomatis tampil sebagai kartu.'],
        ['scene' => 'form', 'opts' => ['title' => 'Data baru'], 'title' => 'Semua input lewat jendela (modal)',
            'text' => 'Tombol **Baru** membuka form. Klik baris di daftar untuk mengubah. Kolom bertanda merah wajib diisi.'],
        ['scene' => 'print', 'title' => 'Butuh bantuan? Buka Panduan',
            'text' => 'Setiap modul punya panduan seperti ini. Buka lagi kapan saja lewat tombol **? Panduan** di bagian atas.'],
    ]],

    // ================================================================ MASTER: Gudang
    'MST_WAREHOUSE' => ['title' => 'Gudang & Zona', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Gudang', 'Zona', 'Lokasi'], 'icons' => ['buildings', 'grid-3x3-gap', 'geo-alt']],
            'title' => 'Gudang dibagi menjadi zona', 'text' => 'Satu gudang punya beberapa **zona** (mis. Ambient, Chiller, Dock). Lokasi rak nanti dibuat di dalam zona.'],
        ['scene' => 'form', 'opts' => ['title' => 'Gudang baru'], 'title' => 'Buat gudang',
            'text' => 'Tekan **Baru**, isi kode singkat (mis. **WH1**) dan nama. Kode dipakai di nomor dokumen & label LPN.'],
        ['scene' => 'list', 'opts' => ['q' => 'WH1'], 'title' => 'Tambah zona di halaman gudang',
            'text' => 'Klik gudang untuk membuka detailnya, lalu **Tambah Zona**. Atur kelas suhu zona agar barang tidak salah simpan.'],
    ]],
    'MST_LOC_TYPE' => ['title' => 'Tipe Lokasi', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Tipe Lokasi', 'Kelas Lokasi', 'Lokasi'], 'icons' => ['diagram-3', 'bounding-box', 'geo-alt']],
            'title' => 'Tingkat 1 dari 3', 'text' => 'Tipe menentukan **fungsi** lokasi: Receiving (dock), Storage, Pick Face, Shipping. Sistem memakai fungsi ini untuk saran lokasi.'],
        ['scene' => 'form', 'opts' => ['title' => 'Tipe baru'], 'title' => 'Pilih fungsi dengan benar',
            'text' => 'Dock penerimaan **wajib** bertipe fungsi **Receiving**, jika tidak, lokasi tidak muncul saat terima barang.'],
    ]],
    'MST_LOC_CLASS' => ['title' => 'Kelas Lokasi', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Tipe Lokasi', 'Kelas Lokasi', 'Lokasi'], 'icons' => ['diagram-3', 'bounding-box', 'geo-alt']],
            'title' => 'Kelas = cetakan lokasi', 'text' => 'Kelas menyimpan ukuran, berat maks, **maks LPN**, dan aturan campur item/lot. Semua lokasi di kelas ini ikut aturannya.'],
        ['scene' => 'capacity', 'title' => 'Kapasitas dipakai untuk saran',
            'text' => 'Isi **Maks LPN** / berat / volume. Sistem menghitung isi tiap lokasi dan mengalihkan ke lokasi lain bila penuh.'],
    ]],
    'MST_LOCATION' => ['title' => 'Lokasi', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Zona', 'Kelas', 'Lokasi'], 'icons' => ['grid-3x3-gap', 'bounding-box', 'geo-alt-fill']],
            'title' => 'Setiap lokasi punya zona & kelas', 'text' => 'Kode lokasi biasanya **Aisle-Bay-Level** (mis. A-01-02-1). Urutan pick menentukan rute picking.'],
        ['scene' => 'list', 'opts' => ['q' => 'A-01'], 'title' => 'Buat banyak lokasi sekaligus',
            'text' => 'Pakai **Generate Lokasi**: tentukan rentang aisle, bay, dan level. Ratusan lokasi jadi dalam sekali klik.'],
        ['scene' => 'capacity', 'title' => 'Pantau kapasitas',
            'text' => 'Kolom **Kapasitas** menunjukkan isi lokasi (hijau → kuning → merah). Kosongkan kapasitas di form agar mengikuti kelas.'],
    ]],
    'MST_PALLET' => ['title' => 'Jenis Pallet', 'slides' => [
        ['scene' => 'scan', 'opts' => ['target' => 'pallet'], 'title' => 'Catat jenis pallet',
            'text' => 'Isi ukuran, berat kosong (tare), dan **beban maks**. Dipakai saat membuat LPN dan menghitung berat pallet.'],
        ['scene' => 'toggle', 'title' => 'Nonaktifkan, jangan hapus',
            'text' => 'Pallet yang sudah tidak dipakai cukup **dinonaktifkan**. Data lama tetap aman.'],
    ]],
    'MST_LPN' => ['title' => 'LPN', 'slides' => [
        ['scene' => 'scan', 'opts' => ['target' => 'pallet'], 'title' => 'LPN = lokasi bergerak',
            'text' => 'LPN adalah label yang ditempel di pallet. Barang di atasnya ikut LPN dari **penerimaan sampai pengiriman**.'],
        ['scene' => 'print', 'title' => 'Generate label sekaligus',
            'text' => 'Tekan **Generate** untuk membuat banyak nomor LPN berurutan, lalu cetak & tempel di pallet kosong.'],
        ['scene' => 'form', 'opts' => ['title' => 'Registrasi LPN'], 'title' => 'Atau daftar manual / saat terima',
            'text' => 'Label dari supplier bisa didaftarkan lewat **Registrasi Manual**, atau otomatis saat di-scan di penerimaan. LPN rusak? **Void**.'],
    ]],

    // ================================================================ MASTER: Mitra
    'MST_OWNER' => ['title' => 'Owner', 'slides' => [
        ['scene' => 'partners', 'title' => 'Pemilik barang',
            'text' => 'Owner adalah **pemilik stok**. Gudang 3PL bisa menyimpan barang banyak owner; stok tiap owner terpisah.'],
        ['scene' => 'form', 'opts' => ['title' => 'Owner baru'], 'title' => 'Buat owner dulu',
            'text' => 'Item, supplier, dan dokumen terhubung ke owner. Buat owner **sebelum** membuat item.'],
    ]],
    'MST_SUPPLIER' => ['title' => 'Supplier', 'slides' => [
        ['scene' => 'partners', 'title' => 'Asal barang masuk',
            'text' => 'Supplier dipilih di dokumen **Receipt (Inbound)**. Kosongkan owner bila supplier dipakai bersama.'],
        ['scene' => 'form', 'opts' => ['title' => 'Supplier baru'], 'title' => 'Lengkapi kontak',
            'text' => 'Isi **PIC & telepon** agar tim gudang mudah menghubungi saat ada selisih barang.'],
    ]],
    'MST_CUSTOMER' => ['title' => 'Customer', 'slides' => [
        ['scene' => 'partners', 'title' => 'Tujuan barang keluar',
            'text' => 'Customer dipakai di dokumen **Outbound**, dan di Inbound untuk barang **retur dari customer**.'],
        ['scene' => 'list', 'opts' => ['q' => 'Alamat'], 'title' => 'Satu customer, banyak alamat',
            'text' => 'Buka customer lalu **Tambah Alamat Kirim**. Tandai satu sebagai alamat utama.'],
    ]],

    // ================================================================ MASTER: Produk
    'MST_ITEM_GROUP' => ['title' => 'Item Group', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Item Group', 'Item Class', 'Item'], 'icons' => ['collection', 'tags', 'box-seam']],
            'title' => 'Tingkat 1 dari 3', 'text' => 'Group adalah kategori besar (mis. **Makanan**, **Minuman**). Dipakai untuk laporan & aturan gudang.'],
        ['scene' => 'form', 'opts' => ['title' => 'Group baru'], 'title' => 'Buat group',
            'text' => 'Cukup kode & nama. Setelah itu buat **Item Class** di bawahnya.'],
    ]],
    'MST_ITEM_CLASS' => ['title' => 'Item Class', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Item Group', 'Item Class', 'Item'], 'icons' => ['collection', 'tags', 'box-seam']],
            'title' => 'Tingkat 2 dari 3', 'text' => 'Class adalah sub-kategori (mis. Makanan → **Snack**, **Mie Instan**). Setiap item wajib punya class.'],
        ['scene' => 'form', 'opts' => ['title' => 'Class baru'], 'title' => 'Pilih group induk',
            'text' => 'Pilih **Item Group** induknya, lalu isi kode & nama class.'],
    ]],
    'MST_ITEM' => ['title' => 'Item', 'slides' => [
        ['scene' => 'hierarchy', 'opts' => ['labels' => ['Item Group', 'Item Class', 'Item'], 'icons' => ['collection', 'tags', 'box-seam']],
            'title' => 'Tingkat 3: barangnya', 'text' => 'Item = SKU milik satu owner. Tentukan **lot/expired tracking** dan aturan rotasi (**FEFO/FIFO**).'],
        ['scene' => 'uom', 'title' => 'Atur satuan & barcode',
            'text' => 'Buka item → **Tambah UOM**: 1 CTN = 24 PCS. Isi **barcode** per kemasan agar scan karton otomatis memilih CTN.'],
        ['scene' => 'scan', 'opts' => ['target' => 'carton'], 'title' => 'Barcode = kunci scan',
            'text' => 'Barcode satuan & kemasan harus unik. Operator men-scan barcode ini saat terima barang.'],
    ]],
    'MST_UOM' => ['title' => 'UOM', 'slides' => [
        ['scene' => 'uom', 'title' => 'Satuan dasar & kemasan',
            'text' => 'Buat satuan seperti **PCS, BOX, CTN, PLT**. Konversi (1 CTN = 24 PCS) diatur per item.'],
        ['scene' => 'toggle', 'title' => 'Desimal hanya untuk timbang',
            'text' => 'Aktifkan **Boleh desimal** hanya untuk KG / LTR. Satuan hitung (PCS, CTN) harus bilangan bulat.'],
    ]],

    // ================================================================ MASTER: Pengguna
    'MST_USER' => ['title' => 'User', 'slides' => [
        ['scene' => 'roles', 'title' => 'Setiap user punya role',
            'text' => 'Role menentukan menu & tombol yang terlihat. Anda hanya bisa mengelola user dengan **level di bawah** Anda.'],
        ['scene' => 'form', 'opts' => ['title' => 'User baru'], 'title' => 'Buat akun',
            'text' => 'Isi nama, **username** untuk login, role, dan password awal. Minta user mengganti password setelah login pertama.'],
        ['scene' => 'toggle', 'title' => 'Karyawan keluar? Nonaktifkan',
            'text' => 'User nonaktif tidak bisa login, tetapi riwayat transaksinya tetap tercatat.'],
    ]],
    'MST_ROLE' => ['title' => 'Role & Hak Akses', 'slides' => [
        ['scene' => 'roles', 'title' => 'Hierarki role',
            'text' => '**Manager > SPV > Admin > Operator**. Role hanya bisa mengatur role di bawah levelnya.'],
        ['scene' => 'matrix', 'title' => 'Atur hak akses per menu',
            'text' => 'Buka role → centang **Lihat / Tambah / Ubah / Hapus / Setujui** untuk setiap menu, lalu **Simpan**.'],
    ]],

    // ================================================================ INBOUND
    'INB_RECEIPT' => ['title' => 'Receipt (ASN)', 'slides' => [
        ['scene' => 'truck', 'title' => 'Rencana barang datang',
            'text' => 'Receipt (ASN) dibuat **sebelum** barang tiba: dari PO supplier atau retur customer.'],
        ['scene' => 'doc', 'title' => 'Header & detail',
            'text' => '**Header** = info dokumen (supplier, tanggal, referensi). **Detail** = item & qty yang diharapkan.'],
        ['scene' => 'form', 'opts' => ['title' => 'Receipt baru'], 'title' => 'Konfirmasi agar bisa diterima',
            'text' => 'Selama **Draft** masih bisa diubah. Tekan **Konfirmasi** → status **Open**, dan dokumen muncul di Receiving.'],
    ]],
    'INB_RECEIVING' => ['title' => 'Receiving', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'RCV-WH1'], 'title' => 'Pilih dokumen',
            'text' => 'Daftar berisi dokumen **Open / Receiving**. Klik untuk mulai menerima. Operator gudang memakai **Tampilan Mobile**.'],
        ['scene' => 'scan', 'opts' => ['target' => 'carton'], 'title' => 'Terima per pallet',
            'text' => 'Pilih dock (sistem menyarankan yang masih muat), scan **LPN**, scan barang, isi qty, lot, expired, kondisi.'],
        ['scene' => 'capacity', 'title' => 'Dock penuh? Dialihkan',
            'text' => 'Pallet baru otomatis diarahkan ke dock yang kosong. Semua penuh? Tekan **Beri tahu Admin**.'],
        ['scene' => 'doc', 'title' => 'Selesai terima',
            'text' => 'Setelah semua diterima tekan **Selesai Terima**. Selisih (kurang/lebih/rusak) tercatat otomatis.'],
    ]],
    'INB_INTRANSIT' => ['title' => 'Intransit In', 'slides' => [
        ['scene' => 'dock', 'title' => 'Barang masih di dock',
            'text' => 'Intransit In menampilkan barang yang **sudah diterima tapi belum disimpan** ke rak, diurutkan dari yang **paling lama**.'],
        ['scene' => 'list', 'opts' => ['q' => 'LPN-WH1'], 'title' => 'Pantau umur di dock',
            'text' => 'Badge **kuning** = lebih dari 4 jam di dock. Klik kartu dock atau nomor LPN untuk menyaring isi satu pallet.'],
        ['scene' => 'undo', 'title' => 'Salah terima? Batalkan',
            'text' => 'Centang baris → **Batal terima** + alasan. Stok dock dikurangi dan qty **kembali ke Receiving** untuk diterima ulang.'],
        ['scene' => 'doc', 'title' => 'Dokumen dibuka ulang otomatis',
            'text' => 'Bila dokumen sudah **Received**, statusnya kembali **Receiving** dan selisihnya dihitung ulang saat selesai.'],
    ]],
    'INB_PUTAWAY' => ['title' => 'Putaway', 'slides' => [
        ['scene' => 'dock', 'title' => 'Pallet menunggu di dock',
            'text' => 'Tab **Menunggu** berisi pallet (LPN) yang sudah diterima tapi belum disimpan, urut dari yang **paling lama**.'],
        ['scene' => 'putaway', 'title' => 'Sistem menyarankan lokasi',
            'text' => 'Klik **Putaway** atau scan LPN. Lokasi saran dipilih yang **muat**, suhunya sesuai, tidak campur barang, dan langsung **dipesan**.'],
        ['scene' => 'capacity', 'title' => 'Lokasi penuh? Butuh SPV',
            'text' => 'Boleh pilih alternatif atau ketik lokasi lain. Lokasi penuh / campur item / suhu beda butuh **persetujuan SPV**.'],
        ['scene' => 'doc', 'title' => 'Dokumen tertutup otomatis',
            'text' => 'Setelah semua pallet sebuah dokumen disimpan, receipt **Received** otomatis menjadi **Closed**. Riwayat ada di tab **Riwayat**.'],
    ]],
    'MST_LOCATING' => ['title' => 'Item Locating', 'slides' => [
        ['scene' => 'hierarchy', 'title' => 'Pilih barangnya',
            'text' => 'Di kiri pilih **Item Group**, **Item Class**, atau **Item**. Aturan paling spesifik menang: **Item > Class > Group**. Badge *ikut group/class* = memakai aturan induk.'],
        ['scene' => 'dnd', 'title' => 'Seret area ke daftar prioritas',
            'text' => '**Seret** zona, aisle, kelas lokasi, atau lokasi dari panel kanan. Urutan atas dicoba duluan; seret kartu untuk mengubah urutan. Atur **level** per area.'],
        ['scene' => 'capacity', 'title' => 'Lihat hasilnya di denah',
            'text' => 'Denah langsung berwarna sesuai prioritas. Klik **nama aisle** atau **kotak lokasi** untuk menambah area. **Hanya area ini** = lokasi lain diblok (perlu SPV).'],
        ['scene' => 'putaway', 'opts' => ['code' => 'A-02-01-1'], 'title' => 'Simulasi lalu simpan',
            'text' => 'Panel **Simulasi** menampilkan saran putaway memakai aturan yang sedang diedit, sebelum disimpan. Klik **Simpan aturan**: putaway berikutnya langsung memakainya.'],
    ]],
    'MST_LABEL' => ['title' => 'Cetak Label', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'LPN / Lokasi'], 'title' => 'Pilih label yang dicetak',
            'text' => 'Tab **LPN** atau **Lokasi**, filter (mis. *belum pernah dicetak*), lalu **centang** baris. Bisa juga cetak semua sesuai filter.'],
        ['scene' => 'qr', 'title' => 'QR Code atau Barcode',
            'text' => '**QR** paling mudah di-scan **kamera HP** (dari sudut mana pun). **Barcode** Code 128 cocok untuk scanner genggam. Pilih *Keduanya* bila perlu.'],
        ['scene' => 'print', 'title' => 'Atur ukuran lalu cetak',
            'text' => 'Pilih ukuran (pallet tag 100x150, 100x50, label rak, 50x25, stiker A4) dan salinan. Lihat **pratinjau**, lalu **Cetak**: di dialog print pakai skala **100%**.'],
        ['scene' => 'toggle', 'title' => 'LPN baru & warna level',
            'text' => '**LPN baru & cetak** membuat nomor LPN kosong sekaligus labelnya. Label lokasi bisa diberi **warna per level** rak agar mudah dibaca dari jauh.'],
    ]],
    'INV_STOCK' => ['title' => 'Inventory', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'SKU / lokasi / LPN'], 'title' => 'Posisi stok saat ini',
            'text' => 'Satu baris = stok di satu **lokasi + LPN + lot + expired + status**. Cari SKU, lokasi, LPN atau lot; filter zona, owner, status & expired.'],
        ['scene' => 'doc', 'title' => 'Klik baris = kartu stok',
            'text' => 'Klik baris untuk melihat detail: on hand, tersedia, alokasi, hold, expired, dan **30 transaksi terakhir** (masuk, putaway, batal, ...).'],
        ['scene' => 'pivot', 'title' => 'Pivot & grafik',
            'text' => 'Tab **Pivot**: stok per SKU x status, per zona x kelompok umur, per owner, dsb. Tab **Grafik** untuk visualnya.'],
        ['scene' => 'excel', 'title' => 'Export Excel',
            'text' => 'Export semua kolom atau **kolom terpilih** untuk stock opname, laporan ke klien, atau rekonsiliasi.'],
    ]],
    'INV_MOVE' => ['title' => 'Pindah Stok', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'lokasi / LPN / SKU'], 'title' => 'Cari stok yang dipindah',
            'text' => 'Cari berdasarkan **lokasi, LPN, SKU atau lot**, lalu klik **Pindah** di baris yang dimaksud.'],
        ['scene' => 'putaway', 'opts' => ['code' => 'A-02-03-2'], 'title' => 'Isi lokasi tujuan',
            'text' => 'Ketik / scan kode lokasi tujuan. Stok ber-**LPN** dipindah **utuh** bersama LPN-nya; stok lepas bisa sebagian (isi qty).'],
        ['scene' => 'doc', 'title' => 'Aturan & persetujuan',
            'text' => 'Stok yang **teralokasi / di-hold** tidak bisa dipindah. Lokasi tujuan penuh, beda suhu atau tidak boleh campur item = **peringatan**; hanya SPV (hak *approve*) yang boleh melanjutkan. Dock, staging & shipping punya alur sendiri.'],
    ]],
    'INV_ADJUST' => ['title' => 'Adjustment', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'lokasi / SKU'], 'title' => 'Pilih baris stok',
            'text' => 'Cari lalu klik **Koreksi** pada baris yang selisih saat stock opname.'],
        ['scene' => 'doc', 'title' => 'Isi qty hasil hitung',
            'text' => 'Masukkan **qty hasil hitung fisik**. Sistem menghitung selisihnya sendiri (Adjust In / Adjust Out). **Alasan wajib** dipilih agar bisa diaudit.'],
        ['scene' => 'putaway', 'opts' => ['code' => 'FOUND'], 'title' => 'Stok temuan',
            'text' => 'Barang ada di rak tetapi belum tercatat? Klik **Stok temuan**, isi lokasi, SKU, qty, lot / expired.'],
    ]],
    'INV_HOLD' => ['title' => 'Hold & Release', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'lokasi / SKU / lot'], 'title' => 'Tahan stok',
            'text' => 'Klik **Hold** untuk menahan sebagian / seluruh stok yang tersedia (QC, recall, investigasi). Stok ter-hold **tidak ikut dialokasikan** ke shipment.'],
        ['scene' => 'doc', 'title' => 'Lepas hold',
            'text' => 'Klik **Release** dan isi qty yang dilepas. Riwayat hold & release tercatat di tab **Riwayat** dan kartu stok.'],
    ]],
    'INV_MAP3D' => ['title' => 'Peta Gudang 3D', 'slides' => [
        ['scene' => 'rack3d', 'title' => 'Gudang dalam 3D',
            'text' => 'Rak dibangun otomatis dari master lokasi: **aisle** = baris rak, **bay** = kolom, **level** = tingkat. Dock & area lantai ada di depan.'],
        ['scene' => 'nav', 'title' => 'Putar, geser, zoom',
            'text' => '**Seret** untuk memutar, klik kanan / dua jari untuk menggeser, scroll / cubit untuk zoom. Tombol **3D / Denah / Depan** untuk sudut pandang cepat.'],
        ['scene' => 'capacity', 'title' => 'Warna = kondisi',
            'text' => 'Ganti **Warna**: kondisi stok (expired, rusak), utilisasi, owner, item, atau umur stok. Atur **Level** untuk melihat rak bawah.'],
        ['scene' => 'putaway', 'opts' => ['code' => 'A-02-03-2'], 'title' => 'Cari & lihat isi',
            'text' => '**Cari** SKU / LPN / lot: lokasinya ditandai pin biru. **Klik slot** untuk melihat isi lokasi, lalu buka di Inventory.'],
    ]],
    'INB_RPT_DETAIL' => ['title' => 'Inbound Detail', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => '30 hari'], 'title' => 'Semua barang yang diterima',
            'text' => 'Satu baris = satu kali terima: item, lot, qty, LPN, dock, sampai **lokasi simpan**. Atur periode & filter di atas.'],
        ['scene' => 'pivot', 'title' => 'Olah data dengan Pivot',
            'text' => 'Tab **Pivot**: pilih **Baris**, **Kolom**, dan **Nilai** (jumlah, rata-rata, hitung unik). Warna lebih gelap = nilai lebih besar.'],
        ['scene' => 'chart', 'title' => 'Jadikan grafik',
            'text' => 'Tab **Grafik** memakai hasil pivot: batang, garis, area, pai, donat. Unduh sebagai **PNG** untuk presentasi.'],
        ['scene' => 'excel', 'title' => 'Export Excel',
            'text' => 'Export **semua kolom**, atau **Pilih kolom...** lalu export hanya kolom yang dibutuhkan. Hasil pivot juga bisa di-export.'],
    ]],
    'INB_RPT_SUMMARY' => ['title' => 'Inbound Summary', 'slides' => [
        ['scene' => 'doc', 'title' => 'Ringkasan per dokumen',
            'text' => 'Rencana vs diterima, **fill rate**, selisih, rusak, **ketepatan waktu** kedatangan, dan progres simpan per receipt.'],
        ['scene' => 'pivot', 'title' => 'Bandingkan supplier',
            'text' => 'Pivot awal: **rata-rata fill rate per pengirim**. Ganti baris ke bulan / owner / status sesuai kebutuhan.'],
        ['scene' => 'chart', 'title' => 'Grafik kinerja',
            'text' => 'Lihat tren dokumen per bulan, atau supplier yang sering terlambat, dalam satu klik.'],
        ['scene' => 'excel', 'title' => 'Export sesuai kebutuhan',
            'text' => 'Pilih kolom yang ingin dikirim ke atasan / supplier, lalu **Export Excel**. Pilihan kolom tersimpan di browser.'],
    ]],

    // ================================================================ OUTBOUND
    'OUT_SHIPMENT' => ['title' => 'Shipment', 'slides' => [
        ['scene' => 'truck', 'title' => 'Pesanan keluar',
            'text' => 'Shipment = barang yang akan **dikirim ke customer**. Pilih owner, customer, dan **alamat kirim**; alamat disalin ke dokumen.'],
        ['scene' => 'doc', 'title' => 'Header & detail',
            'text' => '**Detail** = item & qty pesanan. **Aturan alokasi** (FIFO / FEFO / LIFO / LEFO) bisa dipilih per shipment; kosong = ikut aturan item.'],
        ['scene' => 'form', 'opts' => ['title' => 'Shipment baru'], 'title' => 'Konfirmasi lalu alokasikan',
            'text' => 'Selama **Draft** masih bisa diubah. **Konfirmasi** → **Open**. Lalu tekan **Go Wave** (1 shipment, langsung dialokasikan) atau gabungkan di **Wave Planning**.'],
        ['scene' => 'list', 'opts' => ['q' => 'SHP-WH1'], 'title' => 'Ikuti statusnya',
            'text' => 'Open → Teralokasi → Picking → **Siap Kirim** → Dikirim. Tab **Alokasi & Picking** menunjukkan dari lokasi mana barang diambil.'],
    ]],
    'OUT_WAVE' => ['title' => 'Wave Planning', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'SHP-WH1'], 'title' => 'Gabungkan beberapa shipment',
            'text' => 'Tab **Rencana** berisi shipment **Open**. Centang beberapa → **Buat Wave**. Satu wave = satu pick list yang dikerjakan bersama.'],
        ['scene' => 'toggle', 'title' => 'Pilih aturan pengambilan',
            'text' => '**FIFO** masuk terlama dulu, **FEFO** expired terdekat dulu, **LIFO** masuk terbaru dulu, **LEFO** expired terjauh dulu. Aturan wave menimpa aturan shipment & item.'],
        ['scene' => 'putaway', 'title' => 'Run Wave = alokasi stok',
            'text' => '**Run Wave** mencari stok tersedia di rak/pick face sesuai aturan, memesannya (allocated), dan membuat **pick list** urut rute. Stok kurang? Tampil sebagai *kurang stok*; jalankan **Alokasi sisa** setelah ada putaway baru.'],
        ['scene' => 'undo', 'title' => 'Ubah pikiran?',
            'text' => '**Alokasi ulang** melepas baris yang belum dipick lalu mengalokasikan lagi (bisa ganti aturan). **Batalkan wave** mengembalikan semua shipment ke Open. **Simulasi urutan** memperlihatkan urutan kandidat per aturan.'],
    ]],
    'OUT_PICKING' => ['title' => 'Picking', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'WAV-WH1'], 'title' => 'Antrian wave',
            'text' => 'Wave yang sudah **Teralokasi** muncul di antrian. Klik **Mulai** (desktop) atau kerjakan lewat **Mobile** dengan scanner.'],
        ['scene' => 'scan', 'opts' => ['code' => 'A-01-02-1'], 'title' => 'Ikuti rute, scan, ambil',
            'text' => 'Baris pick diurutkan menurut **urutan pick lokasi**. Di tiap baris: scan **lokasi** → scan **barang** → konfirmasi **qty**. Hasil pick dipindah ke lokasi **staging**.'],
        ['scene' => 'capacity', 'title' => 'Barang kurang? Short pick',
            'text' => 'Isi qty seadanya. Sisa alokasi **dilepas** dan dicatat sebagai *kurang*; butuh persetujuan **SPV**. Qty 0 = tidak ketemu.'],
        ['scene' => 'doc', 'title' => 'Selesai picking',
            'text' => 'Setelah semua baris ditangani, wave **Selesai Pick** dan shipment menjadi **Siap Kirim** di menu **Dispatch**.'],
    ]],
    'OUT_DISPATCH' => ['title' => 'Dispatch & Surat Jalan', 'slides' => [
        ['scene' => 'dock', 'title' => 'Barang di staging',
            'text' => 'Tab **Siap kirim** berisi shipment yang selesai dipick. Centang yang berangkat satu truk → **Buat Surat Jalan** (boleh beberapa customer = multi-drop).'],
        ['scene' => 'form', 'opts' => ['title' => 'Surat Jalan'], 'title' => 'Isi kendaraan & sopir',
            'text' => 'No. kendaraan, nama sopir, ekspedisi. Selama **Draft** masih bisa ditambah / dikeluarkan shipment-nya.'],
        ['scene' => 'print', 'title' => 'Cetak Surat Jalan',
            'text' => '**Cetak** membuka halaman cetak A4: tiap shipment = satu blok (tujuan + barang + kolom cek) dan kolom tanda tangan. Simpan PDF dari dialog print.'],
        ['scene' => 'truck', 'title' => 'Kirim = stok keluar gudang',
            'text' => 'Tekan **Kirim** (butuh hak approve): stok di staging dicatat **SHIP**, shipment **Dikirim**. Tidak bisa dibatalkan setelah itu.'],
    ]],

    'OUT_RPT_DETAIL' => ['title' => 'Outbound Detail', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => '30 hari'], 'title' => 'Semua baris pick',
            'text' => 'Satu baris = satu baris alokasi/pick: shipment, customer, item, lot, **dari lokasi/LPN mana**, aturan (FIFO/FEFO/...), qty dipick vs alokasi, sampai **Surat Jalan**.'],
        ['scene' => 'pivot', 'title' => 'Olah dengan Pivot',
            'text' => 'Contoh: baris **tanggal**, kolom **status pick**, nilai **qty dipick** → terlihat berapa yang kurang (short) per hari atau per picker.'],
        ['scene' => 'chart', 'title' => 'Grafik',
            'text' => 'Hasil pivot bisa langsung jadi grafik batang/garis/pai dan diunduh sebagai PNG.'],
        ['scene' => 'excel', 'title' => 'Export Excel',
            'text' => 'Export semua kolom atau **pilih kolom** (tersimpan di browser). Hasil pivot juga bisa di-export.'],
    ]],
    'OUT_RPT_SUMMARY' => ['title' => 'Outbound Summary', 'slides' => [
        ['scene' => 'doc', 'title' => 'Ringkasan per shipment',
            'text' => 'Pesanan vs teralokasi vs dipick vs dikirim, **fill rate**, baris kurang, **ketepatan kirim** (rencana vs tanggal dispatch), lama proses konfirmasi → kirim.'],
        ['scene' => 'pivot', 'title' => 'Bandingkan customer',
            'text' => 'Pivot awal: **rata-rata fill rate per customer**. Ganti baris ke bulan / owner / aturan alokasi sesuai kebutuhan.'],
        ['scene' => 'chart', 'title' => 'Grafik kinerja',
            'text' => 'Lihat tren shipment per bulan atau customer yang sering terlambat dalam satu klik.'],
        ['scene' => 'excel', 'title' => 'Export sesuai kebutuhan',
            'text' => 'Pilih kolom lalu **Export Excel** untuk laporan ke atasan / customer.'],
    ]],

    // ================================================================ MOBILE (operator)
    'M_PICKING' => ['title' => 'Picking', 'slides' => [
        ['scene' => 'list', 'opts' => ['q' => 'WAV-WH1'], 'title' => 'Pilih wave',
            'text' => 'Ketuk wave dari antrian atau scan **nomor wave**. Pilih **staging** tempat menaruh hasil pick.'],
        ['scene' => 'scan', 'opts' => ['code' => 'A-01-02-1'], 'title' => 'Pergi ke lokasi, scan rak',
            'text' => 'Layar menunjukkan **lokasi** berikutnya sesuai rute. Sampai di rak, scan **label lokasi**. Scan rak lain yang juga ada di daftar? Langsung pindah ke baris itu.'],
        ['scene' => 'scan', 'opts' => ['target' => 'carton'], 'title' => 'Scan barang, konfirmasi qty',
            'text' => 'Scan **barcode barang** atau **LPN** pallet, cek qty, lalu **Konfirmasi pick**. Kurang? Isi qty seadanya (butuh SPV).'],
        ['scene' => 'capacity', 'title' => 'Ulangi sampai selesai',
            'text' => 'Setelah baris terakhir, wave selesai dan barang menunggu di **staging**. Admin membuat **Surat Jalan** di desktop.'],
    ]],
    'M_PUTAWAY' => ['title' => 'Putaway', 'slides' => [
        ['scene' => 'scan', 'opts' => ['target' => 'pallet'], 'title' => 'Scan pallet di dock',
            'text' => 'Scan **label LPN** pallet, atau ketuk dari daftar. Ambil pallet yang **paling lama** di dock dulu.'],
        ['scene' => 'putaway', 'title' => 'Ikuti lokasi saran',
            'text' => 'Layar menampilkan **lokasi tujuan** dengan huruf besar. Lokasi ini sudah dipesan untuk Anda. Terhalang? Pilih alternatif.'],
        ['scene' => 'scan', 'opts' => ['code' => 'A-01-02-1'], 'title' => 'Taruh, lalu scan lokasi',
            'text' => 'Letakkan pallet di rak, lalu scan **label lokasi**. Bila beda dengan tujuan, sistem minta konfirmasi.'],
        ['scene' => 'capacity', 'title' => 'Selesai, lanjut pallet berikutnya',
            'text' => 'Stok pindah ke rak, pallet hilang dari daftar dock. Lokasi penuh? Pilih lokasi lain atau panggil SPV.'],
    ]],
];
