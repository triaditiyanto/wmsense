@extends('layouts.app')
@section('title', 'Item Group')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_ITEM_GROUP', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_ITEM_GROUP" modal="modalGroup" :action="route('master.item-groups.store')" title="Item Group Baru" :record="['is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Item Group</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama...', 'filters' => ['active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']]]])
    @include('partials.pager', ['paginator' => $groups])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Keterangan</th><th class="text-end">Item Class</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($groups as $g)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalGroup"
                    data-action="{{ route('master.item-groups.update', $g) }}" data-method="PUT" data-title="Ubah {{ $g->code }}"
                    data-record="{{ json_encode($g->only(['code', 'name', 'description', 'is_active'])) }}" @endif>
                <td class="fw-semibold">{{ $g->code }}</td>
                <td>{{ $g->name }}</td>
                <td class="text-muted">{{ $g->description }}</td>
                <td class="text-end"><a href="{{ route('master.item-classes.index', ['group' => $g->id]) }}" onclick="event.stopPropagation()">{{ $g->item_classes_count }}</a></td>
                <td><x-active-badge :active="$g->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="5" class="o-empty"><i class="bi bi-collection"></i>Belum ada item group.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalGroup" title="Item Group">
        <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
        <x-field label="Nama" required><input name="name" class="form-control"></x-field>
        <x-field label="Keterangan"><textarea name="description" rows="2" class="form-control"></textarea></x-field>
        <x-active-switch />
    </x-modal-form>
@endpush
