@extends('layouts.app')
@section('title', 'Owner')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_OWNER', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_OWNER" modal="modalOwner" :action="route('master.owners.store')" title="Owner Baru" :record="['is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Owner</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama owner...', 'filters' => ['active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']]]])
    @include('partials.pager', ['paginator' => $owners])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>PIC</th><th>Telepon</th><th>Email</th><th class="text-end">Item</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($owners as $o)
            @php($c = $o->contact ?? [])
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalOwner"
                    data-action="{{ route('master.owners.update', $o) }}" data-method="PUT" data-title="Ubah Owner {{ $o->code }}"
                    data-record="{{ json_encode([
                        'code' => $o->code, 'name' => $o->name, 'is_active' => $o->is_active,
                        'contact[pic]' => $c['pic'] ?? null, 'contact[phone]' => $c['phone'] ?? null,
                        'contact[email]' => $c['email'] ?? null, 'contact[address]' => $c['address'] ?? null,
                    ]) }}" @endif>
                <td class="fw-semibold">{{ $o->code }}</td>
                <td>{{ $o->name }}</td>
                <td>{{ $c['pic'] ?? '' }}</td>
                <td>{{ $c['phone'] ?? '' }}</td>
                <td class="text-muted">{{ $c['email'] ?? '' }}</td>
                <td class="text-end">{{ number_format($o->items_count) }}</td>
                <td><x-active-badge :active="$o->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="o-empty"><i class="bi bi-person-badge"></i>Belum ada owner.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalOwner" title="Owner" size="modal-lg">
        <div class="o-group mb-0">
            <div>
                <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
                <x-field label="Nama" required><input name="name" class="form-control"></x-field>
                <x-active-switch />
            </div>
            <div>
                <x-field label="PIC"><input name="contact[pic]" class="form-control"></x-field>
                <x-field label="Telepon"><input name="contact[phone]" class="form-control"></x-field>
                <x-field label="Email"><input type="email" name="contact[email]" class="form-control"></x-field>
            </div>
        </div>
        <x-field label="Alamat"><textarea name="contact[address]" rows="2" class="form-control"></textarea></x-field>
    </x-modal-form>
@endpush
