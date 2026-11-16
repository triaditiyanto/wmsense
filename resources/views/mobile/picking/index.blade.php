@extends('layouts.mobile')
@section('title', 'Picking')
@section('subtitle', 'Rak → staging')
@section('back', route('m.home'))

@php($canDo = auth()->user()->hasAccess('OUT_PICKING', 'create'))

@section('content')
<div class="m-card d-flex align-items-center gap-2 py-2">
    <span class="small text-muted">Wave menunggu dipick</span>
    <span class="fw-semibold ms-auto">{{ $queue->count() }}</span>
    <button type="button" class="m-help" data-guide-open><i class="bi bi-question-circle"></i> Panduan</button>
</div>

@if (! $canDo)
    <div class="m-empty"><i class="bi bi-lock"></i>Anda tidak punya akses mengerjakan picking.</div>
@else
<ol class="m-stepper m-stepper-3" id="stepper">
    @foreach ([1 => 'Wave', 2 => 'Lokasi', 3 => 'Barang'] as $n => $label)
        <li><button type="button" data-go="{{ $n }}"><span class="m-stepper-dot">{{ $n }}</span><span class="m-stepper-lbl">{{ $label }}</span></button></li>
    @endforeach
</ol>
<div class="m-chips" id="chips"></div>

<div class="m-wiz">
    {{-- ======================================================= 1. WAVE --}}
    <section class="m-panel" data-step="1">
        <div class="m-coach">
            <div class="m-coach-ico is-scan"><i class="bi bi-collection"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Wave mana yang dikerjakan?</div>
                <div class="m-coach-text">Ketuk wave dari daftar, atau scan <b>nomor wave</b> di pick list. Baris pick sudah diurutkan sesuai <b>rute</b> rak.</div>
                <button type="button" class="m-coach-more" data-guide-open><i class="bi bi-play-circle"></i> Lihat contoh</button>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="1"></div>
        <button type="button" class="m-bigscan" data-scan="wave"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan wave</span></button>
        <div class="m-scanbar mb-0"><input id="fWave" class="form-control font-monospace text-uppercase" placeholder="atau ketik nomor wave" enterkeyhint="go"></div>

        <div class="m-section-title">Antrian</div>
        <div class="d-grid gap-2" id="waveList">
            @forelse ($queue as $w)
                <button type="button" class="m-pick" data-id="{{ $w->id }}">
                    <span class="min-w-0"><b class="font-monospace">{{ $w->wave_no }}</b>
                        <span class="d-block small text-muted text-truncate">{{ $w->shipments->pluck('shipment_no')->implode(', ') }}</span></span>
                    <span class="text-end small">
                        @if ($w->status->value === 'PICKING')<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">{{ $w->done_count }}/{{ $w->open_count + $w->done_count }}</span>
                        @else<span class="text-muted">{{ $w->open_count }} baris</span>@endif
                    </span>
                </button>
            @empty
                <div class="m-empty small py-3"><i class="bi bi-check2-circle"></i>Tidak ada wave menunggu.</div>
            @endforelse
        </div>
    </section>

    {{-- ======================================================= 2. LOKASI --}}
    <section class="m-panel" data-step="2" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-pin"><i class="bi bi-signpost-2"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Pergi ke lokasi ini</div>
                <div class="m-coach-text">Sampai di rak, scan <b>label lokasi</b> untuk memastikan Anda di tempat yang benar.</div>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="2"></div>
        <div class="m-target" id="target2"></div>
        <div class="m-field-label">Staging tujuan <span class="text-muted fw-normal">(hasil pick ditaruh di sini)</span></div>
        <div class="m-chips mb-0" id="stagingList"></div>
        <div class="m-field-label">Sisa baris</div>
        <div class="d-grid gap-2" id="lineList"></div>
    </section>

    {{-- ======================================================= 3. BARANG --}}
    <section class="m-panel" data-step="3" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-scan"><i class="bi bi-upc"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Ambil barangnya</div>
                <div class="m-coach-text">Scan <b>barcode barang</b> (atau LPN pallet), lalu pastikan <b>qty</b> yang diambil. Kurang? Isi qty seadanya.</div>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="3"></div>
        <div class="m-target is-sm" id="target3"></div>
        <button type="button" class="m-bigscan" data-scan="item"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan barang</span></button>
        <div class="m-scanbar mb-2"><input id="fItem" class="form-control font-monospace text-uppercase" placeholder="atau ketik barcode / SKU / LPN" enterkeyhint="go"></div>
        <div class="m-hint" id="hItem"></div>
        <div class="m-card mt-2">
            <div class="m-field-label">Qty diambil <span id="qtyUom" class="text-muted fw-normal"></span></div>
            <div class="input-group input-group-lg">
                <button type="button" class="btn btn-secondary" id="qtyMinus">-</button>
                <input type="number" class="form-control text-center fw-bold" id="fQty" inputmode="decimal" step="any" min="0">
                <button type="button" class="btn btn-secondary" id="qtyPlus">+</button>
            </div>
            <div class="small text-muted mt-1">Diminta: <b id="qtyNeed"></b></div>
        </div>
    </section>

    {{-- ======================================================= SELESAI --}}
    <section class="m-panel m-done" data-step="4" hidden>
        <svg class="m-done-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="23"/><path d="M15 27l7.5 7.5L38 19"/></svg>
        <div class="m-done-title" id="doneTitle">Wave selesai</div>
        <div class="m-done-text" id="doneText"></div>
        <div class="d-grid gap-2 mt-3">
            <button type="button" class="btn btn-primary btn-lg m-btn-lg" id="btnNext"><i class="bi bi-collection me-1"></i> Wave berikutnya</button>
            <a href="{{ route('m.home') }}" class="btn btn-link">Selesai, kembali ke menu</a>
        </div>
    </section>
</div>

<div class="m-sticky-action" id="wizBar" hidden>
    <div class="m-wiz-bar">
        <button type="button" class="btn btn-secondary m-wiz-back" id="btnBack" aria-label="Kembali"><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="btn btn-primary btn-lg flex-grow-1" id="btnMain">Lanjut</button>
    </div>
</div>
@endif

<x-guide code="M_PICKING" />
@endsection

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/scanner.js') }}?v={{ filemtime(public_path('js/scanner.js')) }}"></script>
<script src="{{ asset('js/m-guide.js') }}?v={{ filemtime(public_path('js/m-guide.js')) }}"></script>
<script>
    window.PCK = {
        urls: {
            find: @json(route('outbound.picking.find')),
            waves: @json(url('outbound/picking/waves')),
            lines: @json(url('outbound/picking/lines')),
            refresh: @json(route('m.picking')),
        },
    };
</script>
<script src="{{ asset('js/m-picking.js') }}?v={{ filemtime(public_path('js/m-picking.js')) }}"></script>
@endpush
