@extends('layouts.app')
@section('title', 'Item Locating')

@section('content')
@php
    $canEdit = auth()->user()->can('access', ['MST_LOCATING', 'edit']);
    $canDelete = auth()->user()->can('access', ['MST_LOCATING', 'delete']);
@endphp
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto flex-wrap">
        <div class="o-breadcrumb"><span class="text-muted fw-normal d-none d-md-inline">Master Data</span><span class="sep d-none d-md-inline">/</span><span class="current">Item Locating</span></div>
        @if ($warehouses->count() > 1)
            <select class="form-select form-select-sm w-auto" id="locWarehouse" aria-label="Gudang">
                @foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected($w->id === $warehouse?->id)>{{ $w->code }} - {{ $w->name }}</option>@endforeach
            </select>
        @else
            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">{{ $warehouse?->code }} · {{ $warehouse?->name }}</span>
        @endif
    </div>
    <span class="small text-muted d-none d-lg-inline"><i class="bi bi-diagram-3 me-1"></i>Aturan paling spesifik menang: <b>Item</b> &gt; <b>Item Class</b> &gt; <b>Item Group</b></span>
</div>

@if (! $warehouse)
    <div class="o-panel m-4 p-4 text-center text-muted">Belum ada gudang aktif.</div>
@else
<div class="o-loc" id="locApp">
    {{-- ================= kiri: barang ================= --}}
    <aside class="o-panel o-loc-side">
        <div class="o-loc-head">
            <div class="fw-semibold"><i class="bi bi-boxes me-1"></i>Barang</div>
            <div class="btn-group btn-group-sm o-segment" id="treeFilter">
                <button type="button" class="btn btn-secondary active" data-f="all">Semua</button>
                <button type="button" class="btn btn-secondary" data-f="rule">Ada aturan</button>
            </div>
        </div>
        <div class="px-3 pb-2"><input type="search" class="form-control form-control-sm" id="treeSearch" placeholder="Cari group, class, SKU..."></div>
        <div class="o-loc-tree" id="locTree"><div class="text-muted small p-3">Memuat...</div></div>
    </aside>

    {{-- ================= tengah: editor + denah ================= --}}
    <section class="o-loc-center">
        <div class="o-panel o-loc-editor" id="locEditor">
            <div class="o-loc-empty">
                <i class="bi bi-hand-index-thumb"></i>
                <div class="fw-semibold">Pilih Item Group, Item Class, atau Item di kiri</div>
                <div class="small text-muted">lalu seret area gudang dari kanan ke daftar prioritas.</div>
            </div>
        </div>
        <div class="o-panel o-loc-mapwrap">
            <div class="o-loc-head">
                <div class="fw-semibold"><i class="bi bi-grid-3x3-gap me-1"></i>Denah area simpan</div>
                <div class="o-loc-legend" id="locLegend"></div>
            </div>
            <div class="small text-muted px-3 pb-2"><i class="bi bi-cursor me-1"></i>Klik <b>nama aisle</b> untuk menambah satu aisle, klik <b>kotak lokasi</b> untuk menambah lokasi tertentu. Angka = urutan saran simulasi.</div>
            <div class="o-loc-map" id="locMap"></div>
        </div>
    </section>

    {{-- ================= kanan: palet area + simulasi ================= --}}
    <aside class="o-loc-right">
        <div class="o-panel o-loc-palette">
            <div class="o-loc-head"><div class="fw-semibold"><i class="bi bi-bounding-box me-1"></i>Area gudang</div><span class="small text-muted">seret <i class="bi bi-arrow-left"></i></span></div>
            <div class="o-loc-pal" id="locPalette"></div>
        </div>
        <div class="o-panel o-loc-sim">
            <div class="o-loc-head"><div class="fw-semibold"><i class="bi bi-magic me-1"></i>Simulasi saran putaway</div></div>
            <div class="px-3"><select class="form-select form-select-sm" id="simItem"></select></div>
            <div class="small text-muted px-3 pt-1" id="simSource"></div>
            <div class="o-loc-simlist" id="simList"></div>
        </div>
    </aside>
</div>
@endif
@endsection

@push('scripts')
@if ($warehouse)
<script src="{{ asset('vendor/sortable/Sortable.min.js') }}"></script>
<script>
    window.LOC = {
        warehouse: {{ $warehouse->id }},
        canEdit: @json($canEdit),
        canDelete: @json($canDelete),
        urls: {
            data: @json(route('master.locating-rules.data')),
            save: @json(route('master.locating-rules.save')),
            destroy: @json(route('master.locating-rules.destroy', ['rule' => '__ID__'])),
            simulate: @json(route('master.locating-rules.simulate')),
            page: @json(route('master.locating-rules.index')),
        },
    };
</script>
<script src="{{ asset('js/locating-rules.js') }}?v={{ filemtime(public_path('js/locating-rules.js')) }}"></script>
@endif
@endpush
