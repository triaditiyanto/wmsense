@extends('layouts.app')
@section('title', 'Gudang')

@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_WAREHOUSE" modal="modalWarehouse" :action="route('master.warehouses.store')" title="Gudang Baru"
                      :record="['timezone' => 'Asia/Jakarta', 'is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Gudang &amp; Zona</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama gudang...', 'filters' => ['active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']]]])
    @include('partials.pager', ['paginator' => $warehouses])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Alamat</th><th>Zona Waktu</th><th class="text-end">Zona</th><th class="text-end">Lokasi</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($warehouses as $w)
            <tr class="o-row-link" data-href="{{ route('master.warehouses.show', $w) }}">
                <td class="fw-semibold">{{ $w->code }}</td>
                <td>{{ $w->name }}</td>
                <td class="text-muted text-truncate" style="max-width:280px">{{ $w->address }}</td>
                <td>{{ $timezones[$w->timezone] ?? $w->timezone }}</td>
                <td class="text-end">{{ $w->zones_count }}</td>
                <td class="text-end">{{ number_format($w->locations_count) }}</td>
                <td><x-active-badge :active="$w->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="o-empty"><i class="bi bi-buildings"></i>Belum ada gudang.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    @include('master.warehouses._modal')
@endpush
