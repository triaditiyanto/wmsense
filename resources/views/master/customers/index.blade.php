@extends('layouts.app')
@section('title', 'Customer')

@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_CUSTOMER" modal="modalCustomer" :action="route('master.customers.store')" title="Customer Baru" :record="['is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Customer</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama / kota...', 'filters' => [
        'owner' => ['Owner', ['shared' => 'Semua owner (bersama)'] + $owners->pluck('name', 'id')->all()],
        'active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']],
    ]])
    @include('partials.pager', ['paginator' => $customers])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Owner</th><th>PIC</th><th>Telepon</th><th>Kota</th><th class="text-end">Alamat Kirim</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($customers as $c)
            <tr class="o-row-link" data-href="{{ route('master.customers.show', $c) }}">
                <td class="fw-semibold">{{ $c->code }}</td>
                <td>{{ $c->name }}</td>
                <td>{!! $c->owner ? e($c->owner->name) : '<span class="text-muted">Semua owner</span>' !!}</td>
                <td>{{ $c->pic }}</td>
                <td>{{ $c->phone }}</td>
                <td>{{ $c->city }}</td>
                <td class="text-end">{{ $c->addresses_count }}</td>
                <td><x-active-badge :active="$c->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-people"></i>Belum ada customer.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalCustomer" title="Customer" size="modal-lg">
        @include('master.partners._fields', ['owners' => $owners])
    </x-modal-form>
@endpush
