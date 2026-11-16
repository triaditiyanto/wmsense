@extends('layouts.app')
@section('title', 'UOM')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_UOM', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_UOM" modal="modalUom" :action="route('master.uoms.store')" title="UOM Baru" />
        <div class="o-breadcrumb"><span class="current">UOM (Satuan)</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama...'])
    @include('partials.pager', ['paginator' => $uoms])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Boleh Desimal</th></tr></thead>
        <tbody>
        @forelse ($uoms as $u)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalUom"
                    data-action="{{ route('master.uoms.update', $u) }}" data-method="PUT" data-title="Ubah {{ $u->code }}"
                    data-record="{{ json_encode($u->only(['code', 'name', 'allow_decimal'])) }}" @endif>
                <td class="fw-semibold">{{ $u->code }}</td>
                <td>{{ $u->name }}</td>
                <td>{!! $u->allow_decimal ? '<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">Ya (mis. KG, LTR)</span>' : '<span class="text-muted">Tidak</span>' !!}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="o-empty"><i class="bi bi-rulers"></i>Belum ada UOM.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalUom" title="UOM">
        <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="10" placeholder="PCS"></x-field>
        <x-field label="Nama" required><input name="name" class="form-control" placeholder="Pieces"></x-field>
        <x-field label="Boleh desimal" help="Aktifkan untuk satuan timbang/ukur (KG, LTR). Satuan hitung (PCS, BOX) jangan.">
            <input type="hidden" name="allow_decimal" value="0">
            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="allow_decimal" value="1"></div>
        </x-field>
    </x-modal-form>
@endpush
