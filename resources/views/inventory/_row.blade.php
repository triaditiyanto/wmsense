{{-- Detail satu baris stok + kartu stok (dipanggil InventoryReport::rowDetail, tampil di modal laporan Inventory) --}}
@use('App\Support\Qty')
@php
    $uom = $inv->item->baseUom->code;
    $ref = function ($m) {
        $r = $m->reference;

        return match (true) {
            $r instanceof \App\Models\ReceiptHeader => ['Receipt', $r->receipt_no, route('inbound.receipts.show', $r)],
            $r instanceof \App\Models\TaskHeader => ['Task', $r->task_no, null],
            default => [null, null, null],
        };
    };
    $days = $inv->expiry_date ? (int) now()->startOfDay()->diffInDays($inv->expiry_date, false) : null;
@endphp
<div data-modal-title="{{ $inv->item->sku }} · {{ $inv->location->code }}"></div>
<div class="o-inv-head">
    <div>
        <div class="fw-semibold">{{ $inv->item->sku }} — {{ $inv->item->name }}</div>
        <div class="small text-muted">{{ $inv->owner->name }} · {{ $inv->warehouse->code }} · {{ $inv->location->zone->name }}</div>
    </div>
    <span class="badge rounded-pill bg-{{ $inv->status->value === 'AVAILABLE' ? 'success' : 'warning' }}-subtle text-{{ $inv->status->value === 'AVAILABLE' ? 'success' : 'warning' }}-emphasis">{{ $inv->status->label() }}</span>
</div>
<div class="o-kpis o-kpis-6 my-3">
    <div class="o-kpi"><div class="o-kpi-value">{{ Qty::fmt($inv->qty_on_hand) }}</div><div class="o-kpi-label">On hand ({{ $uom }})</div></div>
    <div class="o-kpi"><div class="o-kpi-value">{{ Qty::fmt($inv->qty_available) }}</div><div class="o-kpi-label">Tersedia</div></div>
    <div class="o-kpi"><div class="o-kpi-value">{{ Qty::fmt($inv->qty_allocated) }} · {{ Qty::fmt($inv->qty_hold) }}</div><div class="o-kpi-label">Alokasi · Hold</div></div>
    <div class="o-kpi"><div class="o-kpi-value font-monospace">{{ $inv->location->code }}</div><div class="o-kpi-label">Lokasi</div></div>
    <div class="o-kpi"><div class="o-kpi-value font-monospace">{{ $inv->lpn?->lpn_no ?? '-' }}</div><div class="o-kpi-label">LPN</div></div>
    <div class="o-kpi"><div class="o-kpi-value {{ $days !== null && $days <= 30 ? 'text-danger' : '' }}">{{ $inv->expiry_date?->format('d/m/Y') ?? '-' }}</div>
        <div class="o-kpi-label">Expired{{ $days !== null ? ' · '.($days < 0 ? 'lewat '.abs($days).' hari' : $days.' hari lagi') : '' }}</div></div>
</div>
<div class="small text-muted mb-2">Lot <b>{{ $inv->lot_no ?? '-' }}</b> · masuk {{ $inv->received_at?->format('d/m/Y H:i') ?? '-' }}</div>

<div class="fw-semibold mb-1"><i class="bi bi-journal-text me-1"></i>Kartu stok <span class="small text-muted fw-normal">(30 transaksi terakhir baris ini)</span></div>
<div class="o-list table-responsive">
    <table class="table table-sm">
        <thead><tr><th>Waktu</th><th>Transaksi</th><th class="text-end">Perubahan</th><th class="text-end">Saldo</th><th>Dokumen</th><th>Oleh</th></tr></thead>
        <tbody>
        @forelse ($moves as $m)
            @php([$refType, $refNo, $refUrl] = $ref($m))
            <tr>
                <td class="text-nowrap small">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td class="o-td-title">{{ $m->type->label() }}</td>
                <td class="text-end fw-semibold {{ Qty::cmp($m->qty_on_hand_change, 0) < 0 ? 'text-danger' : 'text-success' }}">{{ Qty::cmp($m->qty_on_hand_change, 0) > 0 ? '+' : '' }}{{ Qty::fmt($m->qty_on_hand_change) }}</td>
                <td class="text-end">{{ Qty::fmt($m->qty_on_hand_after) }}</td>
                <td class="small">@if ($refNo)@if ($refUrl)<a href="{{ $refUrl }}">{{ $refNo }}</a>@else{{ $refNo }}@endif @endif {{ $m->reason_code ? '· '.$m->reason_code : '' }}</td>
                <td class="small text-muted">{{ $m->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="o-empty">Belum ada transaksi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
