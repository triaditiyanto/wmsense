@extends('layouts.mobile')
@section('title', $receipt->receipt_no)
@section('subtitle', $receipt->senderName() ?: $receipt->owner->name)
@section('back', route('m.receiving.index'))

@php
    $user = auth()->user();
    $canReceive = in_array($receipt->status, \App\Services\ReceivingService::RECEIVABLE, true) && $user->hasAccess('INB_RECEIVING', 'create');
    $steps = [1 => 'Dock', 2 => 'LPN', 3 => 'Item', 4 => 'Detail', 5 => 'Cek'];
@endphp

@section('content')
{{-- Ringkasan progres dokumen --}}
<div class="m-card">
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
        <span class="small text-muted">Progres terima</span>
        <span class="fw-semibold ms-auto" id="pText">{{ $progress['percent'] }}%</span>
        @if ($canReceive)
            <button type="button" class="m-help" id="btnGuide"><i class="bi bi-question-circle"></i> Panduan</button>
        @endif
    </div>
    <div class="progress" style="height:10px"><div class="progress-bar" id="pBar" style="width: {{ $progress['percent'] }}%"></div></div>
    <button class="btn btn-link btn-sm px-0 mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#pList">Lihat per item <i class="bi bi-chevron-down"></i></button>
    <div class="collapse" id="pList"><div id="pItems" class="m-plist"></div></div>
</div>

@if (! $canReceive)
    <div class="m-empty"><i class="bi bi-lock"></i>Dokumen berstatus {{ $receipt->status->label() }} atau Anda tidak punya akses menerima barang.</div>
@else
{{-- Stepper: ketuk langkah yang sudah lewat untuk mengubahnya --}}
<ol class="m-stepper" id="stepper">
    @foreach ($steps as $n => $label)
        <li><button type="button" data-go="{{ $n }}"><span class="m-stepper-dot">{{ $n }}</span><span class="m-stepper-lbl">{{ $label }}</span></button></li>
    @endforeach
</ol>

{{-- Pilihan yang sudah dibuat (ketuk untuk mengubah) --}}
<div class="m-chips" id="chips"></div>

{{-- Muncul bila semua dock penuh --}}
<div class="m-alert-full d-none" id="dockFull">
    <div class="fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Semua dock penuh</div>
    <div class="small">Pallet baru tidak bisa ditaruh. Beri tahu admin untuk cek storage / percepat putaway. Menambah isi pallet yang sudah ada di dock tetap boleh.</div>
    <button type="button" class="btn btn-danger w-100 mt-2" id="btnNotify"><i class="bi bi-bell-fill me-1"></i> Beri tahu Admin</button>
    <button type="button" class="btn btn-link btn-sm w-100" id="btnRecheck">Cek ulang dock</button>
</div>

<form id="rcvForm" autocomplete="off" novalidate>
<div class="m-wiz">
    {{-- ======================================================= 1. DOCK --}}
    <section class="m-panel" data-step="1">
        <div class="m-coach">
            <div class="m-coach-ico is-pin"><i class="bi bi-geo-alt-fill"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Di dock mana pallet ditaruh?</div>
                <div class="m-coach-text">Kartu bertanda <b>Disarankan</b> masih muat. Ketuk kartunya, atau scan label lokasi dock.</div>
                <button type="button" class="m-coach-more" data-guide="1"><i class="bi bi-play-circle"></i> Lihat contoh</button>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="1"></div>

        <div class="m-docks" id="dockList"></div>

        <div class="m-scanbar mt-3 mb-0">
            <input id="fDockCode" class="form-control font-monospace text-uppercase" placeholder="Scan / ketik kode dock" enterkeyhint="next">
            <button class="m-scan-btn" type="button" data-scan="dock" aria-label="Scan lokasi"><i class="bi bi-upc-scan"></i></button>
        </div>
        <div class="m-hint" id="hDock"></div>
    </section>

    {{-- ======================================================= 2. LPN --}}
    <section class="m-panel" data-step="2" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-scan"><i class="bi bi-upc"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Scan label LPN pallet</div>
                <div class="m-coach-text">Scan label yang tertempel di pallet. Belum ada label? Tekan <b>Pallet baru</b>, nomor LPN dibuat otomatis saat Terima.</div>
                <button type="button" class="m-coach-more" data-guide="2"><i class="bi bi-play-circle"></i> Lihat contoh</button>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="2"></div>

        <button type="button" class="m-bigscan" data-scan="lpn"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan LPN</span></button>
        <div class="m-scanbar mb-0">
            <input id="fLpn" class="form-control font-monospace text-uppercase" placeholder="atau ketik nomor LPN" enterkeyhint="next">
        </div>
        <div class="m-hint" id="hLpn"></div>

        <div class="m-or"><span>atau</span></div>
        <button type="button" class="m-choice" id="btnNewLpn">
            <i class="bi bi-plus-square-dotted"></i>
            <span><b>Pallet baru</b><small>Belum ada label. LPN dibuat otomatis</small></span>
            <i class="bi bi-check-circle-fill m-choice-check"></i>
        </button>
    </section>

    {{-- ======================================================= 3. ITEM --}}
    <section class="m-panel" data-step="3" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-scan"><i class="bi bi-box2"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Scan barcode barang</div>
                <div class="m-coach-text">Scan barcode di <b>karton</b> (satuan otomatis CTN) atau di <b>satuan</b>.</div>
                <button type="button" class="m-coach-more" data-guide="3"><i class="bi bi-play-circle"></i> Lihat contoh</button>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="3"></div>

        <button type="button" class="m-bigscan" data-scan="item"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan barang</span></button>
        <div class="m-scanbar mb-0">
            <input id="fItemCode" class="form-control font-monospace" placeholder="atau ketik barcode / SKU" enterkeyhint="next">
        </div>
        <div class="m-hint" id="hItem"></div>

        <button class="btn btn-link btn-sm px-0 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#pickList">
            <i class="bi bi-list-ul"></i> Barcode tidak terbaca? Pilih dari daftar
        </button>
        <div class="collapse" id="pickList"><div class="d-grid gap-2 mt-2" id="pickItems"></div></div>
    </section>

    {{-- ======================================================= 4. DETAIL --}}
    <section class="m-panel" data-step="4" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-count"><i class="bi bi-123"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Hitung &amp; cek kemasan</div>
                <div class="m-coach-text">Isi qty <b>sesuai fisik yang dihitung</b>. Salin lot &amp; expired dari kemasan.</div>
                <button type="button" class="m-coach-more" data-guide="4"><i class="bi bi-play-circle"></i> Lihat contoh</button>
            </div>
        </div>

        <div class="m-item-card">
            <div class="fw-bold" id="iName"></div>
            <div class="small text-muted" id="iMeta"></div>
        </div>

        <div class="m-field-label">Qty</div>
        <div class="m-qty">
            <button type="button" class="m-qty-btn" data-step="-1" aria-label="Kurang"><i class="bi bi-dash-lg"></i></button>
            <input id="fQty" type="number" inputmode="decimal" step="any" min="0" class="form-control" enterkeyhint="next">
            <button type="button" class="m-qty-btn" data-step="1" aria-label="Tambah"><i class="bi bi-plus-lg"></i></button>
            <select id="fUom" class="form-select"></select>
        </div>
        <div class="small text-muted mt-1" id="iBase"></div>
        <div class="m-check d-none" id="cQty"></div>

        <div id="lotWrap">
            <div class="m-field-label">Lot <span class="text-danger d-none" id="lotReq">* wajib</span></div>
            <input id="fLot" class="form-control text-uppercase" enterkeyhint="next" list="lotList">
            <datalist id="lotList"></datalist>
            <div class="m-chips mt-2 mb-0" id="lotChips"></div>
        </div>
        <div id="expWrap">
            <div class="m-field-label">Expired <span class="text-danger d-none" id="expReq">* wajib</span></div>
            <input id="fExp" type="date" class="form-control">
            <div class="m-check d-none" id="cExp"></div>
        </div>

        <div class="m-field-label">Kondisi</div>
        <div class="m-seg" id="fCond">
            @foreach ($conditions as $c)
                <button type="button" class="m-seg-btn {{ $loop->first ? 'active' : '' }}" data-value="{{ $c->value }}">{{ $c->label() }}</button>
            @endforeach
        </div>
        <div class="m-check is-danger d-none" id="cCond"><i class="bi bi-exclamation-octagon-fill"></i><span>Barang rusak masuk sebagai stok <b>DAMAGED</b> (tidak bisa dijual). Pisahkan fisiknya &amp; laporkan ke SPV.</span></div>
    </section>

    {{-- ======================================================= 5. CEK --}}
    <section class="m-panel" data-step="5" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-check"><i class="bi bi-clipboard-check"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Cek sekali lagi</div>
                <div class="m-coach-text">Cocokkan dengan barang di depan Anda. Ada yang salah? Ketuk <b>Ubah</b>. Sudah benar, tekan <b>Terima</b>.</div>
            </div>
        </div>
        <div class="m-card m-review mb-0" id="review"></div>
    </section>

    {{-- ======================================================= SELESAI --}}
    <section class="m-panel m-done" data-step="6" hidden>
        <svg class="m-done-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="23"/><path d="M15 27l7.5 7.5L38 19"/></svg>
        <div class="m-done-title" id="doneTitle">Tersimpan</div>
        <div class="m-done-text" id="doneText"></div>
        <div class="m-check is-success d-none" id="doneAll"><i class="bi bi-trophy-fill"></i><span>Semua item di dokumen sudah diterima lengkap. Kabari admin untuk menyelesaikan dokumen.</span></div>

        <div class="m-scanbar mt-3 mb-2 d-none" id="nextScanWrap">
            <input id="fNextScan" class="form-control font-monospace" placeholder="Scan barang berikutnya" enterkeyhint="go">
        </div>
        <div class="d-grid gap-2 mt-2">
            <button type="button" class="btn btn-primary btn-lg m-btn-lg" id="btnNextItem"><i class="bi bi-upc-scan me-1"></i> Scan barang berikutnya <small class="d-block fw-normal" id="nextItemSub"></small></button>
            <button type="button" class="btn btn-secondary m-btn-lg" id="btnNextPallet"><i class="bi bi-plus-square-dotted me-1"></i> Pallet berikutnya</button>
            <a href="{{ route('m.receiving.index') }}" class="btn btn-link">Selesai, kembali ke daftar</a>
        </div>
    </section>
</div>

<div class="m-sticky-action" id="wizBar">
    <div class="m-wiz-bar">
        <button type="button" class="btn btn-secondary m-wiz-back" id="btnBack" aria-label="Kembali"><i class="bi bi-chevron-left"></i></button>
        <button type="submit" class="btn btn-primary btn-lg flex-grow-1" id="btnNext">Lanjut</button>
    </div>
</div>
</form>

<div class="m-section-title">Diterima barusan</div>
<div id="recentList" class="d-grid gap-2"><div class="m-empty small py-3" id="recentEmpty">Belum ada.</div></div>

@include('mobile.receiving._guide')
@endif
@endsection

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/scanner.js') }}?v={{ filemtime(public_path('js/scanner.js')) }}"></script>
<script src="{{ asset('js/m-guide.js') }}?v={{ filemtime(public_path('js/m-guide.js')) }}"></script>
<script>
    // Konfigurasi untuk public/js/m-receiving.js
    window.RCV = {
        receiptId: {{ $receipt->id }},
        userId: {{ $user->id }},
        progress: @json($progress),
        dockStatus: @json($dockStatus),
        urls: {
            location: @json(route('inbound.receiving.resolve.location', $receipt)),
            lpn: @json(route('inbound.receiving.resolve.lpn', $receipt)),
            item: @json(route('inbound.receiving.resolve.item', $receipt)),
            receive: @json(route('inbound.receiving.receive', $receipt)),
            progress: @json(route('inbound.receiving.progress', $receipt)),
            docks: @json(route('inbound.receiving.docks', $receipt)),
            notify: @json(route('inbound.receiving.docks.notify', $receipt)),
        },
    };
</script>
<script src="{{ asset('js/m-receiving.js') }}?v={{ filemtime(public_path('js/m-receiving.js')) }}"></script>
@endpush
