@extends('layouts.app')
@section('title', 'Receiving')

@use('App\Support\Qty')
@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">Receiving</span></div>
        <span class="text-muted small d-none d-md-inline">Dokumen berstatus Open / Receiving</span>
    </div>
    <a href="{{ route('m.receiving.index') }}" class="btn btn-sm btn-secondary"><i class="bi bi-phone me-1"></i>Tampilan Mobile</a>
    @include('partials.searchbar', ['placeholder' => 'Cari / scan no. receipt, PO, supplier...', 'filters' => [
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
    ]])
    @include('partials.pager', ['paginator' => $receipts])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. Receipt</th><th>Gudang</th><th>Pengirim</th><th>Referensi</th><th>Tgl Datang</th><th style="width:220px">Progres</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($receipts as $r)
            @php($pct = $r->expected_sum > 0 ? min(100, round($r->received_sum / $r->expected_sum * 100)) : 0)
            <tr class="o-row-link" data-href="{{ route('inbound.receiving.show', $r) }}">
                <td class="fw-semibold">{{ $r->receipt_no }}</td>
                <td>{{ $r->warehouse->code }}</td>
                <td>{{ $r->senderName() }}</td>
                <td>{{ $r->reference_no }}</td>
                <td>{{ $r->expected_date?->format('d/m/Y') }}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                        <span class="small text-muted" style="min-width:90px">{{ Qty::fmt($r->received_sum) }} / {{ Qty::fmt($r->expected_sum) }}</span>
                    </div>
                </td>
                <td><span class="badge rounded-pill bg-{{ $r->status->color() }}-subtle text-{{ $r->status->color() }}-emphasis">{{ $r->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="o-empty"><i class="bi bi-inbox"></i>Tidak ada dokumen yang menunggu penerimaan.<br>Konfirmasi receipt (Draft → Open) agar muncul di sini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
