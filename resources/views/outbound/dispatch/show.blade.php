@extends('layouts.app')
@section('title', $do->do_no)

@use('App\Support\Qty')
@use('App\Enums\DeliveryOrderStatus')
@php
    $user = auth()->user();
    $draft = $do->status === DeliveryOrderStatus::DRAFT;
    $canEdit = $draft && $user->can('access', ['OUT_DISPATCH', 'edit']);
    $canDelete = $draft && $user->can('access', ['OUT_DISPATCH', 'delete']);
    $canShip = $draft && $user->can('access', ['OUT_DISPATCH', 'approve']);
    $steps = [DeliveryOrderStatus::DRAFT, DeliveryOrderStatus::DISPATCHED];
    if ($do->status === DeliveryOrderStatus::CANCELLED) $steps = [DeliveryOrderStatus::DRAFT, DeliveryOrderStatus::CANCELLED];
    $currentIndex = array_search($do->status, $steps, true);
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('outbound.dispatch.index', ['tab' => 'do']) }}">Dispatch</a><span class="sep">/</span><span class="current">{{ $do->do_no }}</span>
    </div>
</div>

<div class="o-form-header">
    @if ($canShip)
        <form method="POST" action="{{ route('outbound.dispatch.dispatch', $do) }}" data-ajax data-confirm="Kirim {{ $do->do_no }}? Stok {{ $do->shipments->count() }} shipment keluar dari gudang dan tidak bisa dibatalkan.">
            @csrf <button type="submit" class="btn btn-sm btn-primary" @disabled($do->shipments->isEmpty())><i class="bi bi-truck me-1"></i>Kirim</button>
        </form>
    @endif
    @if ($do->status !== DeliveryOrderStatus::CANCELLED)
        <a href="{{ route('outbound.dispatch.print', $do) }}" target="_blank" class="btn btn-sm btn-secondary"><i class="bi bi-printer me-1"></i>Cetak Surat Jalan</a>
    @endif
    @if ($canEdit)
        <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalDoHeader" data-action="{{ route('outbound.dispatch.update', $do) }}" data-method="PUT" data-title="Ubah {{ $do->do_no }}"
                data-record="{{ json_encode(['ship_date' => $do->ship_date?->format('Y-m-d'), 'vehicle_no' => $do->vehicle_no, 'driver_name' => $do->driver_name, 'driver_phone' => $do->driver_phone, 'carrier' => $do->carrier, 'notes' => $do->notes]) }}">Ubah</button>
        @if ($addable->isNotEmpty())
            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalAddShipment"><i class="bi bi-plus-lg me-1"></i>Tambah shipment</button>
        @endif
    @endif
    @if ($canDelete)
        <form method="POST" action="{{ route('outbound.dispatch.cancel', $do) }}" data-ajax data-confirm="Batalkan {{ $do->do_no }}? Shipment kembali ke daftar siap kirim.">
            @csrf <button type="submit" class="btn btn-sm btn-secondary">Batalkan</button>
        </form>
    @endif
    <div class="o-statusbar">
        @foreach ($steps as $i => $step)
            <span class="o-step {{ $i < $currentIndex ? 'done' : '' }} {{ $i === $currentIndex ? 'current' : '' }} {{ $step === DeliveryOrderStatus::CANCELLED ? 'cancel' : '' }}">{{ $step->label() }}</span>
        @endforeach
    </div>
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        @if ($do->status === DeliveryOrderStatus::CANCELLED)<span class="o-ribbon bg-danger-subtle text-danger-emphasis">Dibatalkan</span>@endif
        <div class="o-doc-label">Surat Jalan / Delivery Order</div>
        <h1 class="o-title">{{ $do->do_no }}</h1>
        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Gudang</span><span class="o-value">{{ $do->warehouse->code }} - {{ $do->warehouse->name }}</span></div>
                <div class="o-field"><span class="o-label">Tgl Kirim</span><span class="o-value">{{ $do->ship_date?->format('d/m/Y') ?? '-' }}</span></div>
                <div class="o-field"><span class="o-label">Ekspedisi</span><span class="o-value">{{ $do->carrier ?? '-' }}</span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">Kendaraan</span><span class="o-value">{{ $do->vehicle_no ?? '-' }}</span></div>
                <div class="o-field"><span class="o-label">Sopir</span><span class="o-value">{{ $do->driver_name ?? '-' }} {{ $do->driver_phone ? '· '.$do->driver_phone : '' }}</span></div>
                <div class="o-field"><span class="o-label">Dikirim</span><span class="o-value">{{ $do->dispatched_at ? $do->dispatchedBy?->name.' · '.$do->dispatched_at->format('d/m/Y H:i') : '-' }}</span></div>
            </div>
        </div>
        @if ($do->notes)<div class="o-field" style="grid-template-columns:150px 1fr"><span class="o-label">Catatan</span><span class="o-value">{{ $do->notes }}</span></div>@endif

        <div class="o-notebook">
            <ul class="nav nav-tabs"><li class="nav-item"><button class="nav-link active" type="button">Shipment & barang <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $do->shipments->count() }} shipment · {{ $totals['lines'] }} baris · {{ Qty::fmt($totals['qty']) }} base</span></button></li></ul>
            <div class="tab-content">
                <div class="tab-pane show active">
                    @forelse ($do->shipments as $s)
                        <div class="d-flex flex-wrap align-items-center gap-2 px-3 pt-3 pb-1">
                            <a href="{{ route('outbound.shipments.show', $s) }}" class="fw-bold text-decoration-none">{{ $s->shipment_no }}</a>
                            <span class="badge rounded-pill bg-{{ $s->status->color() }}-subtle text-{{ $s->status->color() }}-emphasis">{{ $s->status->label() }}</span>
                            <span class="small text-muted">{{ $s->ship_to_name }} · {{ $s->ship_to_address }}</span>
                            @if ($s->reference_no)<span class="small text-muted">Ref {{ $s->reference_no }}</span>@endif
                            @if ($canDelete)
                                <form method="POST" action="{{ route('outbound.dispatch.shipments.remove', [$do, $s]) }}" data-ajax data-confirm="Keluarkan {{ $s->shipment_no }} dari Surat Jalan?" class="ms-auto">
                                    @csrf <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i> Keluarkan</button>
                                </form>
                            @endif
                        </div>
                        <div class="o-list table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead><tr><th style="width:40px">#</th><th>Item</th><th class="text-end">Qty pesan</th><th class="text-end">Qty kirim</th><th>UOM</th><th class="text-end">Base</th><th>Catatan</th></tr></thead>
                                <tbody>
                                @foreach ($s->details as $d)
                                    @php($q = $draft ? $d->qty_picked_base : $d->qty_shipped_base)
                                    <tr class="{{ Qty::cmp($q, 0) <= 0 ? 'text-muted' : '' }}">
                                        <td>{{ $d->line_no }}</td>
                                        <td class="o-td-title"><b>{{ $d->item->sku }}</b> {{ $d->item->name }}</td>
                                        <td class="text-end">{{ Qty::fmt($d->qty_ordered) }}</td>
                                        <td class="text-end fw-semibold">{{ Qty::fmt($d->toOrderUom($q)) }}@if (Qty::cmp($q, $d->qty_ordered_base) < 0)<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis ms-1">kurang</span>@endif</td>
                                        <td>{{ $d->uom->code }}</td>
                                        <td class="text-end text-muted">{{ Qty::fmt($q) }} {{ $d->item->baseUom->code }}</td>
                                        <td class="small text-muted">{{ $d->notes }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="o-empty py-4"><i class="bi bi-inbox"></i>Surat Jalan kosong. Tambahkan shipment siap kirim.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
@if ($canEdit)
<div class="modal fade" id="modalDoHeader" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content o-input" method="POST" data-ajax>
            @csrf <input type="hidden" name="_method" value="PUT">
            <div class="modal-header"><h5 class="modal-title">Surat Jalan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="alert alert-danger form-alert d-none"></div>@include('outbound.dispatch._header_fields')</div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@if ($addable->isNotEmpty())
<div class="modal fade" id="modalAddShipment" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content o-input" method="POST" action="{{ route('outbound.dispatch.shipments.add', $do) }}" data-ajax>
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah shipment ke {{ $do->do_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-list"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th style="width:36px"></th><th>No. Shipment</th><th>Customer / Tujuan</th><th>Tgl Kirim</th></tr></thead>
                    <tbody>
                    @foreach ($addable as $s)
                        <tr><td><input type="checkbox" class="form-check-input" name="shipment_ids[]" value="{{ $s->id }}" id="add-{{ $s->id }}"></td>
                            <td><label for="add-{{ $s->id }}" class="fw-semibold">{{ $s->shipment_no }}</label></td><td>{{ $s->ship_to_name ?? $s->customer->name }}</td><td>{{ $s->expected_ship_date?->format('d/m/Y') }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Tambahkan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endif
@endif
@endpush
