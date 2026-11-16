@extends('layouts.app')
@section('title', 'Item')

@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_ITEM" modal="modalItem" :action="route('master.items.store')" title="Item Baru"
                      :record="['owner_id' => request('owner'), 'item_class_id' => request('class'), 'base_uom_id' => $uoms->firstWhere('code', 'PCS')?->id,
                                'rotation_rule' => 'FIFO', 'temperature_class' => 'AMBIENT', 'is_active' => true]" />
        <div class="o-breadcrumb"><span class="current">Item</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari SKU / nama / barcode...', 'filters' => [
        'owner' => ['Owner', $owners->pluck('name', 'id')->all()],
        'class' => ['Item Class', $classes->pluck('name', 'id')->all()],
        'active' => ['Status', ['1' => 'Aktif', '0' => 'Arsip']],
    ]])
    @include('partials.pager', ['paginator' => $items])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>SKU</th><th>Nama</th><th>Owner</th><th>Group / Class</th><th>Base UOM</th><th>Rotasi</th><th>Pelacakan</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($items as $i)
            <tr class="o-row-link" data-href="{{ route('master.items.show', $i) }}">
                <td class="fw-semibold">{{ $i->sku }}</td>
                <td>{{ $i->name }}</td>
                <td>{{ $i->owner->name }}</td>
                <td class="text-muted">{{ $i->itemClass->itemGroup->name }} › {{ $i->itemClass->name }}</td>
                <td>{{ $i->baseUom->code }}</td>
                <td>{{ $i->rotation_rule->value }}</td>
                <td>
                    @if ($i->is_lot_tracked)<span class="badge rounded-pill bg-info-subtle text-info-emphasis">Lot</span>@endif
                    @if ($i->is_expiry_tracked)<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Expired</span>@endif
                    @if ($i->is_hazardous)<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis">B3</span>@endif
                </td>
                <td><x-active-badge :active="$i->is_active" /></td>
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-box-seam"></i>Tidak ada item.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    @include('master.items._modal')
@endpush
