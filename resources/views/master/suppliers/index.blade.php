@extends('layouts.app')
@section('title', 'Supplier')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_SUPPLIER', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_SUPPLIER" modal="modalSupplier" :action="route('master.suppliers.store')" title="Supplier Baru" :record="['is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Supplier</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama / kota...', 'filters' => [
        'owner' => ['Owner', ['shared' => 'Semua owner (bersama)'] + $owners->pluck('name', 'id')->all()],
        'active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']],
    ]])
    @include('partials.pager', ['paginator' => $suppliers])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Owner</th><th>PIC</th><th>Telepon</th><th>Kota</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($suppliers as $s)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalSupplier"
                    data-action="{{ route('master.suppliers.update', $s) }}" data-method="PUT" data-title="Ubah Supplier {{ $s->code }}"
                    data-record="{{ json_encode($s->only(['code', 'name', 'owner_id', 'tax_id', 'pic', 'phone', 'email', 'address', 'city', 'notes', 'is_active'])) }}" @endif>
                <td class="fw-semibold">{{ $s->code }}</td>
                <td>{{ $s->name }}</td>
                <td>{!! $s->owner ? e($s->owner->name) : '<span class="text-muted">Semua owner</span>' !!}</td>
                <td>{{ $s->pic }}</td>
                <td>{{ $s->phone }}</td>
                <td>{{ $s->city }}</td>
                <td><x-active-badge :active="$s->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="o-empty"><i class="bi bi-truck"></i>Belum ada supplier.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalSupplier" title="Supplier" size="modal-lg">
        @include('master.partners._fields', ['owners' => $owners])
    </x-modal-form>
@endpush
