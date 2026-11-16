@extends('layouts.app')
@section('title', 'Shipment')

@use('App\Support\Qty')
@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        @can('access', ['OUT_SHIPMENT', 'create'])
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalShipmentHeader"
                    data-action="{{ route('outbound.shipments.store') }}" data-method="POST" data-title="Shipment Baru"
                    data-record="{{ json_encode(['warehouse_id' => $warehouses->first()?->id, 'order_date' => now()->format('Y-m-d'), 'expected_ship_date' => now()->format('Y-m-d'), 'priority' => 5]) }}">
                Baru
            </button>
        @endcan
        <div class="o-breadcrumb"><span class="current">Shipment</span></div>
    </div>
    @include('partials.searchbar', [
        'placeholder' => 'Cari no. shipment, referensi, customer...',
        'filters' => [
            'status' => ['Status', collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
            'warehouse_id' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        ],
    ])
    @include('partials.pager', ['paginator' => $shipments])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead>
        <tr>
            <th>No. Shipment</th><th>Gudang</th><th>Owner</th><th>Customer / Tujuan</th><th>Referensi</th>
            <th>Tgl Kirim</th><th>Aturan</th><th class="text-end">Item</th><th>Wave / DO</th><th>Status</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($shipments as $s)
            <tr class="o-row-link" data-href="{{ route('outbound.shipments.show', $s) }}">
                <td class="fw-semibold text-nowrap">{{ $s->shipment_no }}
                    @if ($s->priority <= 2)<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis ms-1" title="Prioritas {{ $s->priority }}">P{{ $s->priority }}</span>@endif
                </td>
                <td>{{ $s->warehouse->code }}</td>
                <td>{{ $s->owner->name }}</td>
                <td class="text-truncate" style="max-width:240px">{{ $s->ship_to_name ?? $s->customer->name }}</td>
                <td>{{ $s->reference_no }}</td>
                <td class="text-nowrap">{{ $s->expected_ship_date?->format('d/m/Y') }}</td>
                <td>{{ $s->allocation_rule?->value ?? '-' }}</td>
                <td class="text-end">{{ $s->details_count }}</td>
                <td class="small text-nowrap">{{ $s->wave?->wave_no ?? '-' }}@if ($s->deliveryOrder)<div class="text-muted">{{ $s->deliveryOrder->do_no }}</div>@endif</td>
                <td><span class="badge rounded-pill bg-{{ $s->status->color() }}-subtle text-{{ $s->status->color() }}-emphasis">{{ $s->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="10" class="o-empty">
                <i class="bi bi-inbox"></i>
                @if (request()->hasAny(['q', 'status', 'warehouse_id'])) Tidak ada shipment yang cocok dengan pencarian.
                @else Belum ada shipment.<br>@can('access', ['OUT_SHIPMENT', 'create']) Klik <b>Baru</b> untuk membuat shipment pertama. @endcan @endif
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    @include('outbound.shipments._header_modal')
@endpush
