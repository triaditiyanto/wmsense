@extends('layouts.app')
@section('title', $customer->name)

@php
    $me = auth()->user();
    $canEdit = $me->can('access', ['MST_CUSTOMER', 'edit']);
    $canAdd = $me->can('access', ['MST_CUSTOMER', 'create']);
    $canDelete = $me->can('access', ['MST_CUSTOMER', 'delete']);
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('master.customers.index') }}">Customer</a><span class="sep">/</span><span class="current">{{ $customer->code }}</span>
    </div>
</div>

<div class="o-form-header">
    @if ($canEdit)
        <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalCustomer"
                data-action="{{ route('master.customers.update', $customer) }}" data-method="PUT" data-title="Ubah Customer {{ $customer->code }}"
                data-record="{{ json_encode($customer->only(['code', 'name', 'owner_id', 'tax_id', 'pic', 'phone', 'email', 'address', 'city', 'notes', 'is_active'])) }}">Ubah</button>
    @endif
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        @unless ($customer->is_active)<span class="o-ribbon bg-secondary-subtle text-secondary-emphasis">Arsip</span>@endunless
        <div class="o-doc-label">Customer · {{ $customer->code }}</div>
        <h1 class="o-title">{{ $customer->name }}</h1>

        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Owner</span><span class="o-value">{{ $customer->owner?->name ?? 'Semua owner (bersama)' }}</span></div>
                <div class="o-field"><span class="o-label">NPWP</span><span class="o-value">{{ $customer->tax_id }}</span></div>
                <div class="o-field"><span class="o-label">Alamat</span><span class="o-value" style="white-space:pre-line">{{ $customer->address }}{{ $customer->city ? "\n".$customer->city : '' }}</span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">PIC</span><span class="o-value">{{ $customer->pic }}</span></div>
                <div class="o-field"><span class="o-label">Telepon</span><span class="o-value">{{ $customer->phone }}</span></div>
                <div class="o-field"><span class="o-label">Email</span><span class="o-value">{{ $customer->email }}</span></div>
            </div>
        </div>

        <div class="o-notebook">
            <ul class="nav nav-tabs">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAddr" type="button">Alamat Kirim ({{ $customer->addresses->count() }})</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabNotes" type="button">Catatan</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabAddr">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Label</th><th>Alamat</th><th>PIC</th><th>Telepon</th><th>Status</th>@if ($canDelete)<th style="width:40px"></th>@endif</tr></thead>
                            <tbody>
                            @forelse ($customer->addresses as $a)
                                <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalAddress"
                                        data-action="{{ route('master.customers.addresses.update', [$customer, $a]) }}" data-method="PUT" data-title="Ubah Alamat {{ $a->label }}"
                                        data-record="{{ json_encode($a->only(['label', 'address', 'city', 'postal_code', 'pic', 'phone', 'is_default', 'is_active'])) }}" @endif>
                                    <td class="fw-semibold">
                                        {{ $a->label }}
                                        @if ($a->is_default)<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis ms-1">Default</span>@endif
                                    </td>
                                    <td>{{ $a->fullAddress() }}</td>
                                    <td>{{ $a->pic }}</td>
                                    <td>{{ $a->phone }}</td>
                                    <td><x-active-badge :active="$a->is_active" /></td>
                                    @if ($canDelete)
                                        <td class="text-end" onclick="event.stopPropagation()">
                                            <form method="POST" action="{{ route('master.customers.addresses.destroy', [$customer, $a]) }}" data-ajax data-confirm="Hapus alamat {{ $a->label }}?">
                                                @csrf <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 o-row-action"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="6" class="o-empty"><i class="bi bi-geo-alt"></i>Belum ada alamat kirim.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($canAdd)
                        <div class="o-add-line">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#modalAddress" data-action="{{ route('master.customers.addresses.store', $customer) }}"
                               data-method="POST" data-title="Tambah Alamat Kirim" data-record="{{ json_encode(['is_active' => true, 'city' => $customer->city]) }}">Tambah alamat</a>
                        </div>
                    @endif
                </div>
                <div class="tab-pane fade" id="tabNotes"><div style="white-space:pre-line">{{ $customer->notes ?: '-' }}</div></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalCustomer" title="Customer" size="modal-lg">
        @include('master.partners._fields', ['owners' => $owners])
    </x-modal-form>

    <x-modal-form id="modalAddress" title="Alamat Kirim" size="modal-lg">
        <div class="o-group mb-0">
            <div>
                <x-field label="Label" required><input name="label" class="form-control" placeholder="mis. Gudang Bekasi"></x-field>
                <x-field label="Kota"><input name="city" class="form-control"></x-field>
                <x-field label="Kode pos"><input name="postal_code" class="form-control"></x-field>
            </div>
            <div>
                <x-field label="PIC"><input name="pic" class="form-control"></x-field>
                <x-field label="Telepon"><input name="phone" class="form-control"></x-field>
                <x-field label="Default"><input type="hidden" name="is_default" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_default" value="1"></div></x-field>
                <x-active-switch />
            </div>
        </div>
        <x-field label="Alamat" required><textarea name="address" rows="3" class="form-control"></textarea></x-field>
    </x-modal-form>
@endpush
