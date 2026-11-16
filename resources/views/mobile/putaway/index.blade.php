@extends('layouts.mobile')
@section('title', 'Putaway')
@section('subtitle', 'Dock → rak')
@section('back', route('m.home'))

@use('Carbon\CarbonInterface')
@php($canDo = auth()->user()->hasAccess('INB_PUTAWAY', 'create'))

@section('content')
<div class="m-card d-flex align-items-center gap-2 py-2">
    <span class="small text-muted">Pallet menunggu di dock</span>
    <span class="fw-semibold ms-auto" id="pendingCount">{{ $pending->count() }}</span>
    <button type="button" class="m-help" data-guide-open><i class="bi bi-question-circle"></i> Panduan</button>
</div>

@if (! $canDo)
    <div class="m-empty"><i class="bi bi-lock"></i>Anda tidak punya akses mengerjakan putaway.</div>
@else
<ol class="m-stepper m-stepper-3" id="stepper">
    @foreach ([1 => 'Pallet', 2 => 'Saran', 3 => 'Simpan'] as $n => $label)
        <li><button type="button" data-go="{{ $n }}"><span class="m-stepper-dot">{{ $n }}</span><span class="m-stepper-lbl">{{ $label }}</span></button></li>
    @endforeach
</ol>
<div class="m-chips" id="chips"></div>

<div class="m-wiz">
    {{-- ======================================================= 1. PALLET --}}
    <section class="m-panel" data-step="1">
        <div class="m-coach">
            <div class="m-coach-ico is-scan"><i class="bi bi-upc"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Pallet mana yang dipindah?</div>
                <div class="m-coach-text">Scan <b>label LPN</b> pada pallet di dock, atau ketuk pallet dari daftar. Ambil yang <b>paling lama</b> dulu.</div>
                <button type="button" class="m-coach-more" data-guide-open><i class="bi bi-play-circle"></i> Lihat contoh</button>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="1"></div>
        <button type="button" class="m-bigscan" data-scan="lpn"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan LPN</span></button>
        <div class="m-scanbar mb-0"><input id="fLpn" class="form-control font-monospace text-uppercase" placeholder="atau ketik nomor LPN" enterkeyhint="go"></div>

        <div class="m-section-title">Di dock sekarang</div>
        <div class="d-grid gap-2" id="pendingList">
            @forelse ($pending as $p)
                <button type="button" class="m-pick" data-lpn="{{ $p->lpn_no }}">
                    <span class="min-w-0"><b class="font-monospace">{{ $p->lpn_no }}</b>
                        <span class="d-block small text-muted text-truncate">{{ $p->location_code }} · {{ $p->skus }}</span></span>
                    <span class="text-end small">
                        @if ($p->task)<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Dikerjakan</span>
                        @else<span class="text-muted">{{ \Carbon\Carbon::parse($p->received_at)->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, true, 1) }}</span>@endif
                    </span>
                </button>
            @empty
                <div class="m-empty small py-3"><i class="bi bi-check2-circle"></i>Dock kosong. Semua barang sudah disimpan.</div>
            @endforelse
        </div>
    </section>

    {{-- ======================================================= 2. SARAN --}}
    <section class="m-panel" data-step="2" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-pin"><i class="bi bi-signpost-2"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Bawa pallet ke lokasi ini</div>
                <div class="m-coach-text">Sistem memilih lokasi yang <b>muat</b>, suhunya sesuai, dan tidak campur barang. Lokasi ini sudah <b>dipesan</b> untuk Anda.</div>
            </div>
        </div>
        <div class="m-target" id="target"></div>
        <div class="m-field-label">Lokasi lain (bila saran terhalang)</div>
        <div class="m-chips mb-0" id="altList"></div>

        <div class="m-field-label">Isi pallet</div>
        <div class="d-grid gap-2" id="contents"></div>
        <button type="button" class="btn btn-link btn-sm text-danger px-0 mt-2" id="btnCancelTask">Batalkan task (pallet tetap di dock)</button>
    </section>

    {{-- ======================================================= 3. SIMPAN --}}
    <section class="m-panel" data-step="3" hidden>
        <div class="m-coach">
            <div class="m-coach-ico is-scan"><i class="bi bi-geo-alt"></i></div>
            <div class="min-w-0">
                <div class="m-coach-title">Taruh pallet, lalu scan lokasinya</div>
                <div class="m-coach-text">Scan <b>label lokasi</b> di rak tempat pallet diletakkan. Sistem mencocokkan dengan tujuan.</div>
            </div>
        </div>
        <div class="m-coach-note d-none" data-note="3"></div>
        <div class="m-target is-sm" id="target3"></div>
        <button type="button" class="m-bigscan" data-scan="loc"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan lokasi</span></button>
        <div class="m-scanbar mb-0"><input id="fLoc" class="form-control font-monospace text-uppercase" placeholder="atau ketik kode lokasi" enterkeyhint="go"></div>
        <div class="m-hint" id="hLoc"></div>
    </section>

    {{-- ======================================================= SELESAI --}}
    <section class="m-panel m-done" data-step="4" hidden>
        <svg class="m-done-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="23"/><path d="M15 27l7.5 7.5L38 19"/></svg>
        <div class="m-done-title" id="doneTitle">Tersimpan</div>
        <div class="m-done-text" id="doneText"></div>
        <div class="d-grid gap-2 mt-3">
            <button type="button" class="btn btn-primary btn-lg m-btn-lg" id="btnNext"><i class="bi bi-upc-scan me-1"></i> Pallet berikutnya</button>
            <a href="{{ route('m.home') }}" class="btn btn-link">Selesai, kembali ke menu</a>
        </div>
    </section>
</div>

<div class="m-sticky-action" id="wizBar" hidden>
    <div class="m-wiz-bar">
        <button type="button" class="btn btn-secondary m-wiz-back" id="btnBack" aria-label="Kembali"><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="btn btn-primary btn-lg flex-grow-1" id="btnMain">Sudah di lokasi, scan <i class="bi bi-chevron-right"></i></button>
    </div>
</div>
@endif

<x-guide code="M_PUTAWAY" />
@endsection

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/scanner.js') }}?v={{ filemtime(public_path('js/scanner.js')) }}"></script>
<script src="{{ asset('js/m-guide.js') }}?v={{ filemtime(public_path('js/m-guide.js')) }}"></script>
<script>
    window.PTW = {
        urls: {
            start: @json(route('inbound.putaway.start')),
            tasks: @json(url('inbound/putaway/tasks')),
            refresh: @json(route('m.putaway')),
        },
    };
</script>
<script src="{{ asset('js/m-putaway.js') }}?v={{ filemtime(public_path('js/m-putaway.js')) }}"></script>
@endpush
