@extends('layouts.app')
@section('title', 'Item Class')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_ITEM_CLASS', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_ITEM_CLASS" modal="modalClass" :action="route('master.item-classes.store')" title="Item Class Baru"
                      :record="['item_group_id' => request('group', $groups->first()?->id), 'is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Item Class</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode / nama...', 'filters' => [
        'group' => ['Item Group', $groups->pluck('name', 'id')->all()],
        'active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']],
    ]])
    @include('partials.pager', ['paginator' => $classes])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Nama</th><th>Item Group</th><th>Keterangan</th><th class="text-end">Item</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($classes as $c)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalClass"
                    data-action="{{ route('master.item-classes.update', $c) }}" data-method="PUT" data-title="Ubah {{ $c->code }}"
                    data-record="{{ json_encode($c->only(['item_group_id', 'code', 'name', 'description', 'is_active'])) }}" @endif>
                <td class="fw-semibold">{{ $c->code }}</td>
                <td>{{ $c->name }}</td>
                <td>{{ $c->itemGroup->name }}</td>
                <td class="text-muted">{{ $c->description }}</td>
                <td class="text-end"><a href="{{ route('master.items.index', ['class' => $c->id]) }}" onclick="event.stopPropagation()">{{ $c->items_count }}</a></td>
                <td><x-active-badge :active="$c->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="6" class="o-empty"><i class="bi bi-diagram-2"></i>Belum ada item class.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalClass" title="Item Class">
        <x-field label="Item group" required>
            <select name="item_group_id" class="form-select">@foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->code }} - {{ $g->name }}</option>@endforeach</select>
        </x-field>
        <x-field label="Kode" required><input name="code" class="form-control text-uppercase" maxlength="20"></x-field>
        <x-field label="Nama" required><input name="name" class="form-control"></x-field>
        <x-field label="Keterangan"><textarea name="description" rows="2" class="form-control"></textarea></x-field>
        <x-active-switch />
    </x-modal-form>
@endpush
