@extends('layouts.app')
@section('title', 'Peta Gudang 3D')

@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto min-w-0 flex-wrap">
        <div class="o-breadcrumb"><span class="text-muted fw-normal d-none d-md-inline">Inventory</span><span class="sep d-none d-md-inline">/</span><span class="current">Peta Gudang 3D</span></div>
        @if ($warehouses->count() > 1)
            <select class="form-select form-select-sm w-auto" id="mapWarehouse" aria-label="Gudang">
                @foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected($w->id === $warehouse?->id)>{{ $w->code }} - {{ $w->name }}</option>@endforeach
            </select>
        @else
            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">{{ $warehouse?->code }} · {{ $warehouse?->name }}</span>
        @endif
        <div class="btn-group btn-group-sm o-segment" id="mapViews">
            <button type="button" class="btn btn-secondary active" data-view="3d"><i class="bi bi-box me-1"></i>3D</button>
            <button type="button" class="btn btn-secondary" data-view="top"><i class="bi bi-grid-3x3 me-1"></i>Denah</button>
            <button type="button" class="btn btn-secondary" data-view="front"><i class="bi bi-layout-three-columns me-1"></i>Depan</button>
        </div>
    </div>
    <form class="o-scan-input o-map-search" id="mapSearch" autocomplete="off">
        <i class="bi bi-search"></i>
        <input type="search" id="mapQuery" class="form-control form-control-sm" placeholder="Cari SKU, lokasi, LPN, lot...">
    </form>
    <select class="form-select form-select-sm w-auto" id="mapMode" aria-label="Warna">
        <option value="stock">Warna: Kondisi stok</option>
        <option value="fill">Warna: Utilisasi lokasi</option>
        <option value="owner">Warna: Owner</option>
        <option value="item">Warna: Item (SKU)</option>
        <option value="age">Warna: Umur stok</option>
    </select>
    <button type="button" class="btn btn-sm btn-secondary" id="mapRefresh" title="Muat ulang data"><i class="bi bi-arrow-clockwise"></i></button>
</div>

@if (! $warehouse)
    <div class="o-panel p-4 text-center text-muted">Belum ada gudang aktif.</div>
@else
<div class="o-map" id="mapRoot">
    <div class="o-map-stage" id="mapStage">
        <div class="o-map-loading" id="mapLoading"><span class="spinner-border spinner-border-sm me-2"></span>Membangun gudang 3D...</div>

        <div class="o-map-kpis" id="mapKpis"></div>

        <div class="o-map-tools">
            <label class="o-map-tool"><span>Level</span>
                <select class="form-select form-select-sm" id="mapLevel"><option value="0">Semua</option></select>
            </label>
            <label class="o-map-tool form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="mapOnlyFilled">
                <span>Hanya terisi</span>
            </label>
            <label class="o-map-tool form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="mapLabels" checked>
                <span>Label</span>
            </label>
        </div>

        <div class="o-map-legend" id="mapLegend"></div>
        <div class="o-map-hint d-none d-md-block"><i class="bi bi-mouse me-1"></i>Seret = putar · klik kanan / 2 jari = geser · scroll = zoom · klik rak = lihat isi</div>
        <div class="o-map-tip" id="mapTip" hidden></div>
        <div class="o-map-results" id="mapResults" hidden></div>
    </div>

    <aside class="o-map-panel" id="mapPanel" hidden>
        <div class="o-map-panel-head">
            <div class="min-w-0">
                <div class="o-map-panel-code font-monospace" id="mpCode"></div>
                <div class="small text-muted text-truncate" id="mpTitle"></div>
            </div>
            <button type="button" class="btn-close" id="mpClose" aria-label="Tutup"></button>
        </div>
        <div class="o-map-panel-body" id="mpBody"></div>
    </aside>
</div>
@endif
@endsection

@push('scripts')
@if ($warehouse)
<script type="importmap">
    { "imports": {
        "three": "{{ asset('vendor/three/three.module.min.js') }}",
        "three/addons/controls/OrbitControls.js": "{{ asset('vendor/three/OrbitControls.js') }}"
    } }
</script>
<script>
    window.WMAP = {
        warehouse: {{ $warehouse->id }},
        urls: {
            data: @json(route('inventory.map.data')),
            location: @json(route('inventory.map.location', ['location' => '__ID__'])),
            page: @json(route('inventory.map.index')),
        },
        canStock: @json(auth()->user()->can('access', ['INV_STOCK', 'view'])),
    };
</script>
<script type="module" src="{{ asset('js/warehouse-3d.js') }}?v={{ filemtime(public_path('js/warehouse-3d.js')) }}"></script>
@endif
@endpush
