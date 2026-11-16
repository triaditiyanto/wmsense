@extends('layouts.app')
@section('title', 'Jenis Pallet')

@use('App\Support\Qty')
@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_PALLET', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_PALLET" modal="modalPallet" :action="route('master.pallets.store')" title="Jenis Pallet Baru"
                      :record="['material' => 'WOOD', 'is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Jenis Pallet</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama...', 'filters' => ['active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']]]])
    @include('partials.pager', ['paginator' => $pallets])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Material</th><th>Dimensi (cm)</th><th class="text-end">Berat Kosong</th><th class="text-end">Beban Maks</th><th class="text-end">Tinggi Tumpuk Maks</th><th class="text-end">LPN</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($pallets as $p)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalPallet"
                    data-action="{{ route('master.pallets.update', $p) }}" data-method="PUT" data-title="Ubah {{ $p->code }}"
                    data-record="{{ json_encode([...$p->only(['code', 'name', 'length_cm', 'width_cm', 'height_cm', 'tare_weight_kg', 'max_load_kg', 'max_stack_height_cm', 'description', 'is_active']), 'material' => $p->material->value]) }}" @endif>
                <td class="fw-semibold">{{ $p->code }}</td>
                <td>{{ $p->name }}</td>
                <td>{{ $p->material->label() }}</td>
                <td class="text-muted">@if ($p->length_cm){{ Qty::fmt($p->length_cm) }} × {{ Qty::fmt($p->width_cm) }} × {{ Qty::fmt($p->height_cm) }}@endif</td>
                <td class="text-end">{{ $p->tare_weight_kg ? Qty::fmt($p->tare_weight_kg).' kg' : '' }}</td>
                <td class="text-end">{{ $p->max_load_kg ? Qty::fmt($p->max_load_kg).' kg' : '' }}</td>
                <td class="text-end">{{ $p->max_stack_height_cm ? Qty::fmt($p->max_stack_height_cm).' cm' : '' }}</td>
                <td class="text-end">{{ number_format($p->lpns_count) }}</td>
                <td><x-active-badge :active="$p->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="9" class="o-empty"><i class="bi bi-stack"></i>Belum ada jenis pallet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalPallet" title="Jenis Pallet" size="modal-lg">
        <div class="o-group mb-0">
            <div>
                <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
                <x-field label="Nama" required><input name="name" class="form-control" placeholder="Pallet Kayu 120x100"></x-field>
                <x-field label="Material" required>
                    <select name="material" class="form-select">
                        @foreach ($materials as $m)<option value="{{ $m->value }}">{{ $m->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Berat kosong (kg)"><input type="number" step="any" min="0" name="tare_weight_kg" class="form-control"></x-field>
                <x-field label="Beban maks (kg)"><input type="number" step="any" min="0" name="max_load_kg" class="form-control"></x-field>
            </div>
            <div>
                <x-field label="Panjang (cm)"><input type="number" step="any" min="0" name="length_cm" class="form-control"></x-field>
                <x-field label="Lebar (cm)"><input type="number" step="any" min="0" name="width_cm" class="form-control"></x-field>
                <x-field label="Tinggi (cm)" help="Tinggi pallet kosong."><input type="number" step="any" min="0" name="height_cm" class="form-control"></x-field>
                <x-field label="Tumpuk maks (cm)" help="Tinggi pallet + barang."><input type="number" step="any" min="0" name="max_stack_height_cm" class="form-control"></x-field>
                <x-active-switch />
            </div>
        </div>
        <x-field label="Keterangan"><textarea name="description" rows="2" class="form-control"></textarea></x-field>
    </x-modal-form>
@endpush
