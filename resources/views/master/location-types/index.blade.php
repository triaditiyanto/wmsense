@extends('layouts.app')
@section('title', 'Tipe Lokasi')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_LOC_TYPE', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_LOC_TYPE" modal="modalType" :action="route('master.location-types.store')" title="Tipe Lokasi Baru"
                      :record="['location_function' => 'STORAGE', 'is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Tipe Lokasi</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama...', 'filters' => [
        'function' => ['Fungsi', collect($functions)->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all()],
        'active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']],
    ]])
    @include('partials.pager', ['paginator' => $types])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Fungsi Sistem</th><th>Keterangan</th><th class="text-end">Kelas</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($types as $t)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalType"
                    data-action="{{ route('master.location-types.update', $t) }}" data-method="PUT" data-title="Ubah Tipe {{ $t->code }}"
                    data-record="{{ json_encode(['code' => $t->code, 'name' => $t->name, 'location_function' => $t->location_function->value, 'description' => $t->description, 'is_active' => $t->is_active]) }}" @endif>
                <td class="fw-semibold">{{ $t->code }}</td>
                <td>{{ $t->name }}</td>
                <td><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">{{ $t->location_function->label() }}</span></td>
                <td class="text-muted">{{ $t->description }}</td>
                <td class="text-end">{{ $t->location_classes_count }}</td>
                <td><x-active-badge :active="$t->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="6" class="o-empty"><i class="bi bi-tags"></i>Belum ada tipe lokasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalType" title="Tipe Lokasi">
        <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
        <x-field label="Nama" required><input name="name" class="form-control"></x-field>
        <x-field label="Fungsi sistem" required help="Menentukan perilaku sistem: stok di STORAGE / PICK FACE bisa dialokasikan, RECEIVING untuk barang datang, dst.">
            <select name="location_function" class="form-select">
                @foreach ($functions as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="Keterangan"><textarea name="description" rows="2" class="form-control"></textarea></x-field>
        <x-active-switch />
    </x-modal-form>
@endpush
