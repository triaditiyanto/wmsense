@extends('layouts.app')
@section('title', $report->title())

@use('App\Support\Qty')
@section('content')
@php
    $key = $report->key();
    $route = fn ($name, $extra = []) => $report->route($name, array_merge(request()->query(), $extra));
    $rowLink = (bool) $report->rowKey();
    $fmt = function ($v, $type) {
        if ($v === null || $v === '') return '';
        return match ($type) {
            'number' => Qty::fmt($v),
            'percent' => str_replace('.', ',', (string) (float) $v).'%',
            'date' => \Carbon\Carbon::parse($v)->format('d/m/Y'),
            'datetime' => \Carbon\Carbon::parse($v)->format('d/m/Y H:i'),
            default => $v,
        };
    };
@endphp

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto min-w-0">
        <div class="o-breadcrumb"><span class="text-muted fw-normal d-none d-md-inline">{{ $report->section() }}</span><span class="sep d-none d-md-inline">/</span><span class="current">{{ $report->title() }}</span></div>
        <div class="btn-group btn-group-sm o-segment" id="viewTabs">
            <button type="button" class="btn btn-secondary active" data-view="table"><i class="bi bi-table me-1"></i>Tabel</button>
            <button type="button" class="btn btn-secondary" data-view="pivot"><i class="bi bi-grid-3x3 me-1"></i>Pivot</button>
            <button type="button" class="btn btn-secondary" data-view="chart"><i class="bi bi-bar-chart me-1"></i>Grafik</button>
        </div>
    </div>
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ $route('export') }}"><i class="bi bi-layout-three-columns me-2"></i>Semua kolom</a></li>
            <li><a class="dropdown-item" href="#" id="exportSelected"><i class="bi bi-check2-square me-2"></i>Kolom terpilih (<span data-col-count>0</span>)</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalCols"><i class="bi bi-sliders me-2"></i>Pilih kolom...</a></li>
        </ul>
    </div>
    <div data-only="table">@include('partials.pager', ['paginator' => $rows])</div>
</div>

{{-- Filter: periode + filter laporan --}}
<form method="GET" class="o-report-filters" id="filterForm">
    @if ($report->searchColumns())
        <div class="o-rf-field o-rf-search">
            <label>Cari</label>
            <input type="search" name="q" value="{{ $f['q'] }}" class="form-control form-control-sm" placeholder="{{ $report->searchPlaceholder() }}">
        </div>
    @endif
    @if ($report->hasPeriod())
    <div class="o-rf-field">
        <label>{{ $report->periodLabel() }}</label>
        <div class="d-flex align-items-center gap-1">
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $f['from'] }}">
            <span class="text-muted">-</span>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $f['to'] }}">
        </div>
        <div class="o-rf-quick">
            @foreach (['7' => '7 hari', '30' => '30 hari', 'month' => 'Bulan ini', 'last' => 'Bulan lalu', 'year' => 'Tahun ini'] as $q => $label)
                <button type="button" class="btn btn-link btn-sm p-0" data-range="{{ $q }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    @endif
    @foreach ($report->filters() as $name => [$label, $options])
        <div class="o-rf-field">
            <label>{{ $label }}</label>
            <select name="{{ $name }}" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($options as $v => $t)<option value="{{ $v }}" @selected((string) ($f[$name] ?? '') === (string) $v)>{{ $t }}</option>@endforeach
            </select>
        </div>
    @endforeach
    <div class="o-rf-field">
        <label>&nbsp;</label>
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ url()->current() }}" class="btn btn-sm btn-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="o-kpis o-kpis-6">
    @foreach ($kpis as [$label, $value, $icon])
        <div class="o-kpi"><div class="o-kpi-value">{{ $value }}</div><div class="o-kpi-label"><i class="bi bi-{{ $icon }} me-1"></i>{{ $label }}</div></div>
    @endforeach
</div>

{{-- ================================================================ TABEL --}}
<div data-only="table">
    <div class="o-list table-responsive o-report-table">
        <table class="table table-hover table-sm">
            <thead><tr>
                @foreach ($columns as $k => $c)
                    <th data-col="{{ $k }}" class="{{ in_array($c['type'], ['number', 'percent']) ? 'text-end' : '' }}">{{ $c['label'] }}</th>
                @endforeach
            </tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr @if ($rowLink) class="o-row-link" data-row="{{ $row['__row'] }}" @endif>
                    @foreach ($columns as $k => $c)
                        <td data-col="{{ $k }}" class="{{ in_array($c['type'], ['number', 'percent']) ? 'text-end' : '' }} {{ in_array($c['type'], ['date', 'datetime']) ? 'text-nowrap' : '' }}">{{ $fmt($row[$k], $c['type']) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="o-empty"><i class="bi bi-inbox"></i>Tidak ada data pada periode / filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ================================================================ PIVOT --}}
<div data-only="pivot" hidden>
    <div class="o-panel o-analysis">
        <div class="o-analysis-bar">
            <div class="o-rf-field"><label>Baris</label><select class="form-select form-select-sm" id="pvRows"></select></div>
            <button type="button" class="btn btn-sm btn-secondary align-self-end" id="pvSwap" title="Tukar baris & kolom"><i class="bi bi-arrow-left-right"></i></button>
            <div class="o-rf-field"><label>Kolom</label><select class="form-select form-select-sm" id="pvCols"></select></div>
            <div class="o-rf-field"><label>Nilai</label><select class="form-select form-select-sm" id="pvValue"></select></div>
            <div class="o-rf-field"><label>Hitung</label><select class="form-select form-select-sm" id="pvAgg"></select></div>
            <div class="o-rf-field"><label>Urut</label>
                <select class="form-select form-select-sm" id="pvSort"><option value="label">Label (A-Z)</option><option value="desc">Nilai terbesar</option><option value="asc">Nilai terkecil</option></select>
            </div>
            <div class="ms-auto d-flex gap-1 align-self-end">
                <button type="button" class="btn btn-sm btn-secondary" id="pvToChart"><i class="bi bi-bar-chart me-1"></i>Jadikan grafik</button>
                <button type="button" class="btn btn-sm btn-primary" id="pvExport"><i class="bi bi-file-earmark-excel me-1"></i>Export pivot</button>
            </div>
        </div>
        <div class="o-analysis-note" id="pvNote"></div>
        <div class="o-pivot-wrap" id="pvTable"><div class="text-muted p-4 text-center">Memuat data...</div></div>
    </div>
</div>

{{-- ================================================================ GRAFIK --}}
<div data-only="chart" hidden>
    <div class="o-panel o-analysis">
        <div class="o-analysis-bar">
            <div class="o-rf-field"><label>Jenis grafik</label>
                <select class="form-select form-select-sm" id="chType">
                    <option value="bar">Batang</option><option value="bar-stacked">Batang bertumpuk</option><option value="hbar">Batang horizontal</option>
                    <option value="line">Garis</option><option value="area">Area</option><option value="pie">Pai</option><option value="doughnut">Donat</option>
                </select>
            </div>
            <div class="o-rf-field"><label>Tampilkan</label>
                <select class="form-select form-select-sm" id="chTop"><option value="10">10 teratas</option><option value="20">20 teratas</option><option value="50">50 teratas</option><option value="0">Semua</option></select>
            </div>
            <div class="small text-muted align-self-end pb-1" id="chInfo"></div>
            <div class="ms-auto d-flex gap-1 align-self-end">
                <button type="button" class="btn btn-sm btn-secondary" data-goto="pivot"><i class="bi bi-sliders me-1"></i>Atur data (pivot)</button>
                <button type="button" class="btn btn-sm btn-primary" id="chPng"><i class="bi bi-image me-1"></i>Unduh PNG</button>
            </div>
        </div>
        <div class="o-chart-wrap"><canvas id="chCanvas"></canvas></div>
    </div>
</div>
@endsection

@push('modals')
@if ($rowLink)
<div class="modal fade" id="modalRow" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content o-input">
            <div class="modal-header"><h5 class="modal-title">Detail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="modalRowBody"></div>
        </div>
    </div>
</div>
@endif
<div class="modal fade" id="modalCols" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content o-input">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-sliders me-1"></i> Pilih kolom</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <input type="search" class="form-control form-control-sm flex-grow-1" id="colSearch" placeholder="Cari kolom..." style="max-width:220px">
                    <button type="button" class="btn btn-sm btn-secondary" data-cols="all">Semua</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-cols="none">Kosongkan</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-cols="default">Default</button>
                </div>
                <div class="small text-muted mb-2">Dipakai untuk tampilan tabel dan <b>export kolom terpilih</b>. Disimpan di browser ini.</div>
                <div class="o-col-list" id="colList"></div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto"><span data-col-count>0</span> kolom dipilih</span>
                <button type="button" class="btn btn-secondary" id="colApply">Terapkan</button>
                <button type="button" class="btn btn-primary" id="colExport"><i class="bi bi-file-earmark-excel me-1"></i>Terapkan &amp; Export</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script>
    window.REPORT = {
        key: @json($key),
        title: @json($report->title()),
        columns: @json($report->columnsForClient()),
        preset: @json($report->preset()),
        urls: {
            dataset: @json($route('dataset')),
            export: @json($report->route('export')),
            exportPivot: @json($route('export-pivot')),
            row: @json($rowLink ? $report->route('row') : null),
        },
        query: @json(request()->except(['page'])),
    };
</script>
<script src="{{ asset('js/report.js') }}?v={{ filemtime(public_path('js/report.js')) }}"></script>
@endpush
