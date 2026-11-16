@extends('portal.layout')
@section('title', 'WMS untuk gudang Anda')

@section('content')
<section class="p-hero">
    <div>
        <h1>Kelola gudang <span>lebih rapi</span>, dari terima barang sampai kirim.</h1>
        <p>WMSense adalah Warehouse Management System berbasis web: receiving, putaway, inventory 3D, wave &amp; picking, surat jalan, laporan. Satu site = satu database sendiri, siap dipakai dalam satu menit.</p>
        <div class="d-flex flex-wrap gap-2 my-4">
            <a href="{{ route('portal.register') }}" class="btn btn-primary btn-lg px-4"><i class="bi bi-rocket-takeoff me-2"></i>Daftar site baru</a>
            <a href="{{ route('login') }}" class="btn btn-secondary btn-lg px-4">Sudah punya site? Masuk</a>
        </div>
        <div class="p-stats">
            <div class="p-stat"><b>{{ number_format($stats['sites']) }}</b><span>site aktif</span></div>
            <div class="p-stat is-live"><b>{{ number_format($stats['online']) }}</b><span>user sedang online</span></div>
            <div class="p-stat"><b>{{ number_format($stats['logins_today']) }}</b><span>login hari ini</span></div>
        </div>
    </div>
    <div class="p-mock">
        <div class="p-mock-bar"><i></i><i></i><i></i></div>
        <div class="p-mock-row head"><span>Lokasi</span><span>Item</span><span>Lot / Exp</span><span>Qty</span></div>
        <div class="p-mock-row"><b>A-01-02-1</b><span>SNK-001</span><span>LT2608 · 30/11</span><b>180</b></div>
        <div class="p-mock-row"><b>A-02-03-2</b><span>DRK-002</span><span>LT2611 · 02/12</span><b>24</b></div>
        <div class="p-mock-row"><b>PF-03</b><span>DRK-001</span><span>LT2611 · 15/04</span><b>48</b></div>
        <div class="p-mock-row"><b>STG-OUT-01</b><span>NDL-001</span><span>LT2609 · 26/05</span><b>40</b></div>
        <div class="small text-muted mt-2 px-2"><i class="bi bi-cart-check text-primary"></i> Wave WAV-0001 · 5 baris · FEFO · 3 dipick</div>
    </div>
</section>

<section class="p-section" id="fitur">
    <h2 class="mb-3">Semua proses gudang dalam satu aplikasi</h2>
    <div class="p-grid">
        <div class="p-card"><div class="p-ic"><i class="bi bi-box-arrow-in-down"></i></div><h5>Inbound</h5><p>Receipt (ASN), receiving dengan scan di HP, dock otomatis, intransit, putaway dengan saran lokasi & aturan Item Locating.</p></div>
        <div class="p-card"><div class="p-ic"><i class="bi bi-boxes"></i></div><h5>Inventory</h5><p>Posisi stok per lokasi/LPN/lot, kartu stok, dan <b>peta gudang 3D</b> untuk melihat isi rak.</p></div>
        <div class="p-card"><div class="p-ic"><i class="bi bi-box-arrow-up"></i></div><h5>Outbound</h5><p>Shipment, Go Wave / Wave Planning, alokasi FIFO · FEFO · LIFO · LEFO, picking desktop & mobile, Surat Jalan.</p></div>
        <div class="p-card"><div class="p-ic"><i class="bi bi-qr-code-scan"></i></div><h5>Label & scan</h5><p>Cetak label QR / barcode LPN dan lokasi, scan dengan kamera HP atau scanner genggam.</p></div>
        <div class="p-card"><div class="p-ic"><i class="bi bi-bar-chart"></i></div><h5>Laporan</h5><p>Inbound/Outbound detail & summary, pivot, grafik, export Excel. Panduan animasi di tiap modul.</p></div>
        <div class="p-card"><div class="p-ic"><i class="bi bi-shield-lock"></i></div><h5>Satu database per site</h5><p>Data tiap klien terpisah. Login memakai <code>username@kodesite</code>. Role & hak akses per modul.</p></div>
    </div>
</section>

<section class="p-section" id="tipe">
    <h2 class="mb-1">Pilih tipe gudang, master data langsung disiapkan</h2>
    <p class="text-muted mb-3">Saat registrasi, sistem membuat database baru berisi contoh zona, lokasi, item, owner, supplier & customer sesuai tipe gudang Anda. Tanpa transaksi, siap Anda sesuaikan.</p>
    <div class="p-grid">
        @foreach ($types as $t)
            <div class="p-card"><div class="p-ic"><i class="bi bi-{{ $t->icon() }}"></i></div><h5>{{ $t->label() }}</h5><p>{{ $t->description() }}</p></div>
        @endforeach
    </div>
</section>

<section class="p-section">
    <h2 class="mb-3">Mulai dalam 3 langkah</h2>
    <div class="p-steps">
        <div class="p-step"><b>Daftar site</b><div class="text-muted small">Isi nama site, kode site, tipe gudang, dan akun admin.</div></div>
        <div class="p-step"><b>Database dibuat otomatis</b><div class="text-muted small">Migrasi + master data dummy sesuai tipe gudang, beberapa detik saja.</div></div>
        <div class="p-step"><b>Masuk dengan username@kodesite</b><div class="text-muted small">Ubah master data, tambah user, lalu mulai terima barang.</div></div>
    </div>
    <div class="text-center mt-4"><a href="{{ route('portal.register') }}" class="btn btn-primary btn-lg px-5">Daftar sekarang</a></div>
</section>
@endsection
