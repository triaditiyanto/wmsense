@extends('layouts.app')
@section('title', 'Kelas Lokasi')

@use('App\Support\Qty')
@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_LOC_CLASS', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_LOC_CLASS" modal="modalClass" :action="route('master.location-classes.store')" title="Kelas Lokasi Baru"
                      :record="['location_type_id' => $types->first()?->id, 'allow_mixed_items' => true, 'allow_mixed_lots' => true, 'is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Kelas Lokasi</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama...', 'filters' => [
        'type' => ['Tipe', $types->pluck('name', 'id')->all()],
        'active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']],
    ]])
    @include('partials.pager', ['paginator' => $classes])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Penyimpanan</th><th>Dimensi (cm)</th><th class="text-end">Berat Maks</th><th class="text-end">Maks LPN</th><th>Mixing</th><th class="text-end">Lokasi</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($classes as $c)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalClass"
                    data-action="{{ route('master.location-classes.update', $c) }}" data-method="PUT" data-title="Ubah Kelas {{ $c->code }}"
                    data-record="{{ json_encode([
                        'location_type_id' => $c->location_type_id, 'code' => $c->code, 'name' => $c->name, 'storage_type' => $c->storage_type?->value,
                        'length_cm' => $c->length_cm, 'width_cm' => $c->width_cm, 'height_cm' => $c->height_cm,
                        'max_weight_kg' => $c->max_weight_kg, 'max_volume_m3' => $c->max_volume_m3, 'max_lpn' => $c->max_lpn,
                        'allow_mixed_items' => $c->allow_mixed_items, 'allow_mixed_lots' => $c->allow_mixed_lots, 'is_active' => $c->is_active,
                    ]) }}" @endif>
                <td class="fw-semibold">{{ $c->code }}</td>
                <td>{{ $c->name }}</td>
                <td>{{ $c->locationType->name }}</td>
                <td>{{ $c->storage_type?->label() }}</td>
                <td class="text-muted">
                    @if ($c->length_cm){{ Qty::fmt($c->length_cm) }} × {{ Qty::fmt($c->width_cm) }} × {{ Qty::fmt($c->height_cm) }}@endif
                </td>
                <td class="text-end">{{ $c->max_weight_kg ? Qty::fmt($c->max_weight_kg).' kg' : '' }}</td>
                <td class="text-end">{{ $c->max_lpn }}</td>
                <td class="small">
                    <span @class(['text-success' => $c->allow_mixed_items, 'text-muted text-decoration-line-through' => ! $c->allow_mixed_items])>item</span> ·
                    <span @class(['text-success' => $c->allow_mixed_lots, 'text-muted text-decoration-line-through' => ! $c->allow_mixed_lots])>lot</span>
                </td>
                <td class="text-end">{{ number_format($c->locations_count) }}</td>
                <td><x-active-badge :active="$c->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="10" class="o-empty"><i class="bi bi-bounding-box-circles"></i>Belum ada kelas lokasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalClass" title="Kelas Lokasi" size="modal-lg">
        <div class="o-group mb-0">
            <div>
                <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
                <x-field label="Nama" required><input name="name" class="form-control"></x-field>
                <x-field label="Tipe lokasi" required>
                    <select name="location_type_id" class="form-select">
                        @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->location_function->label() }})</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Penyimpanan">
                    <select name="storage_type" class="form-select">
                        <option value=""></option>
                        @foreach ($storageTypes as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Campur item"><input type="hidden" name="allow_mixed_items" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="allow_mixed_items" value="1"></div></x-field>
                <x-field label="Campur lot"><input type="hidden" name="allow_mixed_lots" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="allow_mixed_lots" value="1"></div></x-field>
            </div>
            <div>
                <x-field label="Panjang (cm)"><input type="number" step="any" min="0" name="length_cm" class="form-control"></x-field>
                <x-field label="Lebar (cm)"><input type="number" step="any" min="0" name="width_cm" class="form-control"></x-field>
                <x-field label="Tinggi (cm)"><input type="number" step="any" min="0" name="height_cm" class="form-control"></x-field>
                <x-field label="Berat maks (kg)"><input type="number" step="any" min="0" name="max_weight_kg" class="form-control"></x-field>
                <x-field label="Volume maks (m³)" help="Kosongkan = dihitung dari dimensi."><input type="number" step="any" min="0" name="max_volume_m3" class="form-control"></x-field>
                <x-field label="Maks LPN" help="Jumlah pallet/LPN per lokasi."><input type="number" min="0" name="max_lpn" class="form-control"></x-field>
                <x-active-switch />
            </div>
        </div>
    </x-modal-form>
@endpush
