@extends('layouts.app')
@section('title', $warehouse->name)

@php
    $me = auth()->user();
    $canEdit = $me->can('access', ['MST_WAREHOUSE', 'edit']);
    $canAdd = $me->can('access', ['MST_WAREHOUSE', 'create']);
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('master.warehouses.index') }}">Gudang &amp; Zona</a><span class="sep">/</span><span class="current">{{ $warehouse->code }}</span>
    </div>
</div>

<div class="o-form-header">
    @if ($canEdit)
        <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalWarehouse"
                data-action="{{ route('master.warehouses.update', $warehouse) }}" data-method="PUT" data-title="Ubah Gudang {{ $warehouse->code }}"
                data-record="{{ json_encode($warehouse->only(['code', 'name', 'timezone', 'address', 'is_active'])) }}">Ubah</button>
    @endif
    <a href="{{ route('master.locations.index', ['warehouse' => $warehouse->id]) }}" class="btn btn-sm btn-secondary"><i class="bi bi-grid-3x3 me-1"></i>Lihat Lokasi</a>
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        @unless ($warehouse->is_active)<span class="o-ribbon bg-secondary-subtle text-secondary-emphasis">Arsip</span>@endunless
        <div class="o-doc-label">Gudang</div>
        <h1 class="o-title">{{ $warehouse->name }}</h1>

        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Kode</span><span class="o-value">{{ $warehouse->code }}</span></div>
                <div class="o-field"><span class="o-label">Zona waktu</span><span class="o-value">{{ $timezones[$warehouse->timezone] ?? $warehouse->timezone }}</span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">Alamat</span><span class="o-value" style="white-space:pre-line">{{ $warehouse->address }}</span></div>
            </div>
        </div>

        <div class="o-notebook">
            <ul class="nav nav-tabs"><li class="nav-item"><button class="nav-link active" type="button">Zona <span class="text-muted">({{ $warehouse->zones->count() }})</span></button></li></ul>
            <div class="tab-content">
                <div class="o-list table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Kelas Suhu</th><th class="text-end">Lokasi</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($warehouse->zones as $z)
                            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalZone"
                                    data-action="{{ route('master.warehouses.zones.update', [$warehouse, $z]) }}" data-method="PUT" data-title="Ubah Zona {{ $z->code }}"
                                    data-record="{{ json_encode(['code' => $z->code, 'name' => $z->name, 'temperature_class' => $z->temperature_class->value, 'is_active' => $z->is_active]) }}" @endif>
                                <td class="fw-semibold">{{ $z->code }}</td>
                                <td>{{ $z->name }}</td>
                                <td>{{ $z->temperature_class->label() }}</td>
                                <td class="text-end">{{ number_format($z->locations_count) }}</td>
                                <td><x-active-badge :active="$z->is_active" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="o-empty"><i class="bi bi-bounding-box"></i>Belum ada zona.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($canAdd)
                    <div class="o-add-line">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#modalZone" data-action="{{ route('master.warehouses.zones.store', $warehouse) }}"
                           data-method="POST" data-title="Tambah Zona" data-record="{{ json_encode(['temperature_class' => 'AMBIENT', 'is_active' => true]) }}">Tambah zona</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
    @include('master.warehouses._modal')

    <x-modal-form id="modalZone" title="Zona">
        <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
        <x-field label="Nama" required><input name="name" class="form-control"></x-field>
        <x-field label="Kelas suhu" required>
            <select name="temperature_class" class="form-select">
                @foreach ($temperatures as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
            </select>
        </x-field>
        <x-active-switch />
    </x-modal-form>
@endpush
