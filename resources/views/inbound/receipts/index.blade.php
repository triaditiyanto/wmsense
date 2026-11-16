@extends('layouts.app')
@section('title', 'Receipt')

@section('content')
{{-- ============================== CONTROL PANEL ============================== --}}
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        @can('access', ['INB_RECEIPT', 'create'])
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalReceiptHeader"
                    data-action="{{ route('inbound.receipts.store') }}" data-method="POST" data-title="Receipt Baru"
                    data-record="{{ json_encode(['warehouse_id' => $warehouses->first()?->id, 'receipt_type' => 'PURCHASE', 'expected_date' => now()->format('Y-m-d')]) }}">
                Baru
            </button>
        @endcan
        <div class="o-breadcrumb"><span class="current">Receipt</span></div>
    </div>
    @include('partials.searchbar', [
        'placeholder' => 'Cari no. receipt, referensi, supplier / customer...',
        'filters' => [
            'status' => ['Status', collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
            'warehouse_id' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        ],
    ])
    @include('partials.pager', ['paginator' => $receipts])
</div>

{{-- ============================== LIST VIEW ============================== --}}
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead>
        <tr>
            <th>No. Receipt</th><th>Gudang</th><th>Owner</th><th>Tipe</th><th>Referensi</th>
            <th>Pengirim</th><th>Tgl Datang</th><th class="text-end">Item</th><th>Status</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($receipts as $r)
            <tr class="o-row-link" data-href="{{ route('inbound.receipts.show', $r) }}">
                <td class="fw-semibold">{{ $r->receipt_no }}</td>
                <td>{{ $r->warehouse->code }}</td>
                <td>{{ $r->owner->name }}</td>
                <td>{{ $r->receipt_type->label() }}</td>
                <td>{{ $r->reference_no }}</td>
                <td class="text-truncate" style="max-width:220px">{{ $r->senderName() }}</td>
                <td>{{ $r->expected_date?->format('d/m/Y') }}</td>
                <td class="text-end">{{ $r->details_count }}</td>
                <td><span class="badge rounded-pill bg-{{ $r->status->color() }}-subtle text-{{ $r->status->color() }}-emphasis">{{ $r->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="9" class="o-empty">
                <i class="bi bi-inbox"></i>
                @if (request()->hasAny(['q', 'status', 'warehouse_id']))
                    Tidak ada receipt yang cocok dengan pencarian.
                @else
                    Belum ada dokumen receipt.<br>
                    @can('access', ['INB_RECEIPT', 'create']) Klik <b>Baru</b> untuk membuat receipt pertama. @endcan
                @endif
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    @include('inbound.receipts._header_modal')
@endpush
