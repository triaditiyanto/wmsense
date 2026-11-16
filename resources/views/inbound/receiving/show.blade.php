@extends('layouts.app')
@section('title', 'Receiving '.$receipt->receipt_no)

@use('App\Support\Qty')
@use('App\Enums\ReceiptStatus')
@php
    $user = auth()->user();
    $receivable = in_array($receipt->status, \App\Services\ReceivingService::RECEIVABLE, true);
    $canReceive = $receivable && $user->can('access', ['INB_RECEIVING', 'create']);
    $canCancel = $receipt->status === ReceiptStatus::RECEIVING && $user->can('access', ['INB_RECEIVING', 'delete']);
    $canFinish = $receipt->status === ReceiptStatus::RECEIVING && $user->can('access', ['INB_RECEIVING', 'approve']);
    $steps = [ReceiptStatus::OPEN, ReceiptStatus::RECEIVING, ReceiptStatus::RECEIVED, ReceiptStatus::CLOSED];
    $currentIndex = array_search($receipt->status, $steps, true);
    $activeLines = $receipt->receivings->whereNull('cancelled_at');
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('inbound.receiving.index') }}">Receiving</a><span class="sep">/</span><span class="current">{{ $receipt->receipt_no }}</span>
    </div>
    <a href="{{ route('m.receiving.show', $receipt) }}" class="btn btn-sm btn-secondary"><i class="bi bi-phone me-1"></i>Mobile</a>
</div>

<div class="o-form-header">
    @if ($canReceive)
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalReceive"
                data-action="{{ route('inbound.receiving.receive', $receipt) }}" data-method="POST" data-title="Terima Barang"
                data-record="{{ json_encode(['condition' => 'GOOD', 'location_id' => $docks->first()?->id]) }}">
            <i class="bi bi-box-arrow-in-down me-1"></i>Terima Barang
        </button>
    @endif
    @if ($canFinish)
        <form method="POST" action="{{ route('inbound.receiving.finish', $receipt) }}" data-ajax
              data-confirm="Selesaikan penerimaan {{ $receipt->receipt_no }}? Selisih (kurang/lebih/rusak) akan dicatat dan barang tidak bisa diterima lagi.">
            @csrf <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-check2-all me-1"></i>Selesai Terima</button>
        </form>
    @endif
    <a href="{{ route('inbound.receipts.show', $receipt) }}" class="btn btn-sm btn-secondary">Lihat Dokumen</a>

    <div class="o-statusbar">
        @foreach ($steps as $i => $step)
            <span class="o-step {{ $currentIndex !== false && $i < $currentIndex ? 'done' : '' }} {{ $i === $currentIndex ? 'current' : '' }}">{{ $step->label() }}</span>
        @endforeach
    </div>
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        <div class="o-doc-label">Penerimaan Barang</div>
        <h1 class="o-title">{{ $receipt->receipt_no }}</h1>

        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">{{ $receipt->receipt_type->usesCustomer() ? 'Customer' : 'Supplier' }}</span><span class="o-value">{{ $receipt->senderName() }}</span></div>
                <div class="o-field"><span class="o-label">Owner</span><span class="o-value">{{ $receipt->owner->name }}</span></div>
                <div class="o-field"><span class="o-label">Gudang</span><span class="o-value">{{ $receipt->warehouse->code }} - {{ $receipt->warehouse->name }}</span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">No. Referensi</span><span class="o-value">{{ $receipt->reference_no }}</span></div>
                <div class="o-field"><span class="o-label">Tgl Kedatangan</span><span class="o-value">{{ $receipt->expected_date?->format('d/m/Y') }}</span></div>
                <div class="o-field"><span class="o-label">Progres</span><span class="o-value">
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar" style="width: {{ $progress['percent'] }}%"></div></div>
                        <span>{{ $progress['percent'] }}%</span>
                    </div>
                </span></div>
            </div>
        </div>

        <div class="o-notebook">
            <ul class="nav nav-tabs">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProgress" type="button">Progres Item</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHistory" type="button">Riwayat Terima ({{ $activeLines->count() }})</button></li>
                @if ($receipt->discrepancies->isNotEmpty())
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDiff" type="button">Selisih ({{ $receipt->discrepancies->count() }})</button></li>
                @endif
            </ul>
            <div class="tab-content">
                {{-- ===== Progres per item ===== --}}
                <div class="tab-pane fade show active" id="tabProgress">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Item</th><th class="text-end">Diharapkan</th><th class="text-end">Diterima</th><th class="text-end">Rusak</th><th class="text-end">Sisa</th><th style="width:200px">Progres</th></tr></thead>
                            <tbody>
                            @foreach ($progress['items'] as $p)
                                <tr>
                                    <td><span class="fw-semibold">[{{ $p['sku'] }}]</span> {{ $p['name'] }}
                                        @if ($p['lot_required'])<span class="badge rounded-pill bg-info-subtle text-info-emphasis ms-1">Lot</span>@endif
                                        @if ($p['expiry_required'])<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Exp</span>@endif
                                    </td>
                                    <td class="text-end">{{ Qty::fmt($p['expected']) }} {{ $p['base_uom'] }}</td>
                                    <td class="text-end fw-semibold">{{ Qty::fmt($p['received']) }}</td>
                                    <td class="text-end {{ $p['damaged'] > 0 ? 'text-danger' : 'text-muted' }}">{{ Qty::fmt($p['damaged']) }}</td>
                                    <td class="text-end">{{ Qty::fmt($p['remaining']) }}</td>
                                    <td>
                                        <div class="progress" style="height:8px">
                                            <div class="progress-bar {{ $p['received'] > $p['expected'] ? 'bg-info' : ($p['percent'] >= 100 ? 'bg-success' : '') }}" style="width: {{ $p['percent'] }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ===== Riwayat terima ===== --}}
                <div class="tab-pane fade" id="tabHistory">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Waktu</th><th>Item</th><th class="text-end">Qty</th><th>Lot</th><th>Expired</th><th>Kondisi</th><th>LPN</th><th>Dock</th><th>Oleh</th>@if ($canCancel)<th></th>@endif</tr></thead>
                            <tbody>
                            @forelse ($receipt->receivings as $r)
                                <tr @class(['text-muted text-decoration-line-through' => $r->isCancelled()]) @if ($r->isCancelled()) title="Dibatalkan {{ $r->cancelledBy?->name }}: {{ $r->cancel_reason }}" @endif>
                                    <td class="small">{{ $r->received_at->format('d/m H:i') }}</td>
                                    <td><span class="fw-semibold">{{ $r->item->sku }}</span></td>
                                    <td class="text-end">{{ Qty::fmt($r->qty) }} {{ $r->uom->code }} @if ($r->conversion_factor > 1)<span class="small text-muted">({{ Qty::fmt($r->qty_base) }})</span>@endif</td>
                                    <td>{{ $r->lot_no }}</td>
                                    <td>{{ $r->expiry_date?->format('d/m/Y') }}</td>
                                    <td><span class="badge rounded-pill bg-{{ $r->condition->color() }}-subtle text-{{ $r->condition->color() }}-emphasis">{{ $r->condition->label() }}</span></td>
                                    <td class="font-monospace small">{{ $r->lpn->lpn_no }}</td>
                                    <td class="font-monospace small">{{ $r->location->code }}</td>
                                    <td class="small">{{ $r->receivedBy?->name }} <i class="bi bi-{{ $r->source === 'MOBILE' ? 'phone' : 'display' }} text-muted"></i></td>
                                    @if ($canCancel)
                                        <td class="text-end">
                                            @unless ($r->isCancelled())
                                                <form method="POST" action="{{ route('inbound.receiving.cancel', [$receipt, $r]) }}" data-ajax data-confirm="Batalkan terima {{ Qty::fmt($r->qty) }} {{ $r->uom->code }} {{ $r->item->sku }}? Stok di LPN {{ $r->lpn->lpn_no }} akan dikurangi.">
                                                    @csrf <button type="submit" class="btn btn-sm btn-link text-danger p-0 o-row-action" title="Batalkan"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                            @endunless
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="10" class="o-empty"><i class="bi bi-upc-scan"></i>Belum ada barang diterima.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ===== Selisih ===== --}}
                @if ($receipt->discrepancies->isNotEmpty())
                    <div class="tab-pane fade" id="tabDiff">
                        <div class="o-list table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>Baris</th><th>Item</th><th>Jenis</th><th class="text-end">Diharapkan</th><th class="text-end">Diterima</th><th class="text-end">Selisih</th></tr></thead>
                                <tbody>
                                @foreach ($receipt->discrepancies as $d)
                                    <tr>
                                        <td>#{{ $d->detail->line_no }}</td>
                                        <td><span class="fw-semibold">{{ $d->item->sku }}</span> {{ $d->item->name }}</td>
                                        <td><span class="badge rounded-pill bg-{{ $d->type->color() }}-subtle text-{{ $d->type->color() }}-emphasis">{{ $d->type->label() }}</span></td>
                                        <td class="text-end">{{ Qty::fmt($d->qty_expected_base) }}</td>
                                        <td class="text-end">{{ Qty::fmt($d->qty_received_base) }}</td>
                                        <td class="text-end fw-semibold">{{ $d->qty_diff_base > 0 && $d->type->value === 'OVER' ? '+' : '' }}{{ Qty::fmt($d->qty_diff_base) }} {{ $d->item->baseUom->code }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
    @if ($canReceive)
        <x-modal-form id="modalReceive" title="Terima Barang" size="modal-lg" submit="Terima">
            <input type="hidden" name="idempotency_key">
            <input type="hidden" name="source" value="DESKTOP">
            <x-field label="Scan barcode" help="Scan barcode item / karton dengan scanner, atau pilih item di bawah.">
                <input id="rcvScan" class="form-control font-monospace" placeholder="Arahkan scanner ke sini lalu scan..." autocomplete="off">
            </x-field>
            <div class="o-group mb-0">
                <div>
                    <x-field label="Item" required>
                        <select name="item_id" id="rcvItem" class="form-select">
                            <option value=""></option>
                            @foreach ($progress['items'] as $p)
                                <option value="{{ $p['item_id'] }}">{{ $p['sku'] }} - {{ $p['name'] }} (sisa {{ Qty::fmt($p['remaining']) }} {{ $p['base_uom'] }})</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field label="Qty" required>
                        <div class="d-flex gap-2">
                            <input type="number" name="qty" step="any" min="0" class="form-control">
                            <select name="uom_id" id="rcvUom" class="form-select" style="max-width:150px"></select>
                        </div>
                    </x-field>
                    <x-field label="Lot"><input name="lot_no" id="rcvLot" class="form-control text-uppercase"></x-field>
                    <x-field label="Expired"><input type="date" name="expiry_date" id="rcvExp" class="form-control"></x-field>
                </div>
                <div>
                    <x-field label="Kondisi" required>
                        <div class="btn-group w-100" role="group">
                            @foreach ($conditions as $c)
                                <input type="radio" class="btn-check" name="condition" id="cond{{ $c->value }}" value="{{ $c->value }}">
                                <label class="btn btn-sm btn-outline-{{ $c->color() }}" for="cond{{ $c->value }}">{{ $c->label() }}</label>
                            @endforeach
                        </div>
                    </x-field>
                    <x-field label="LPN" help="Scan / ketik label LPN. Kosongkan = buat LPN baru otomatis.">
                        <input name="lpn_no" class="form-control font-monospace text-uppercase" placeholder="(LPN baru)">
                    </x-field>
                    <x-field label="Lokasi dock" required>
                        <select name="location_id" class="form-select">
                            @foreach ($docks as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach
                        </select>
                        <div class="form-text" id="dockHint"></div>
                    </x-field>
                    <x-field label="Catatan"><input name="notes" class="form-control"></x-field>
                </div>
            </div>
            <div id="rcvInfo" class="small text-muted mt-2"></div>
            <div class="alert alert-danger d-flex align-items-center gap-2 small mt-3 mb-0 d-none" id="dockAlert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span class="me-auto">Semua dock penuh. Pallet baru tidak bisa ditaruh.</span>
                <button type="button" class="btn btn-sm btn-danger" id="btnNotifyDock"><i class="bi bi-bell-fill me-1"></i>Beri tahu Admin</button>
            </div>
        </x-modal-form>
    @endif
@endpush

@push('scripts')
@if ($canReceive)
<script>
(() => {
    const items = @json(collect($progress['items'])->keyBy('item_id'));
    const resolveUrl = @json(route('inbound.receiving.resolve.item', $receipt));
    const modal = document.getElementById('modalReceive');
    const form = modal.querySelector('form');
    const itemSel = document.getElementById('rcvItem');
    const uomSel = document.getElementById('rcvUom');
    const info = document.getElementById('rcvInfo');

    function applyItem(itemId, uomId = null) {
        const it = items[itemId];
        uomSel.innerHTML = '';
        if (!it) { info.textContent = ''; return; }
        it.uoms.forEach((u) => uomSel.add(new Option(u.factor === '1' ? u.code : `${u.code} (isi ${u.factor})`, u.id)));
        if (uomId) uomSel.value = uomId;
        document.getElementById('rcvLot').required = it.lot_required;
        document.getElementById('rcvExp').required = it.expiry_required;
        info.innerHTML = `Sisa <b>${it.remaining.toLocaleString('id-ID')} ${it.base_uom}</b> dari ${it.expected.toLocaleString('id-ID')}`
            + (it.asn_lots.length ? ` · Lot ASN: ${it.asn_lots.join(', ')}` : '')
            + (it.lot_required ? ' · <span class="text-danger">wajib lot</span>' : '')
            + (it.expiry_required ? ' · <span class="text-danger">wajib expired</span>' : '');
    }
    itemSel.addEventListener('change', () => applyItem(itemSel.value));

    // Scanner USB (keyboard wedge): ketik/scan lalu Enter -> cari item & UOM dari barcode
    document.getElementById('rcvScan').addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const code = e.target.value.trim();
        if (!code) return;
        const r = await WMS.api(resolveUrl, { params: { code } });
        if (!r.ok) { info.innerHTML = `<span class="text-danger">${r.data.message}</span>`; e.target.select(); return; }
        itemSel.value = r.data.item_id;
        applyItem(r.data.item_id, r.data.scanned_uom_id);
        e.target.value = '';
        form.querySelector('[name=qty]').focus();
    });

    // ---- kapasitas dock: label isi, saran bila penuh, tombol notifikasi bila semua penuh
    const dockSel = form.querySelector('[name=location_id]');
    const dockHint = document.getElementById('dockHint');
    const docksUrl = @json(route('inbound.receiving.docks', $receipt));
    const notifyUrl = @json(route('inbound.receiving.docks.notify', $receipt));
    async function refreshDocks(autoSwitch) {
        const r = await WMS.api(docksUrl, { params: { current: dockSel.value } });
        if (!r.ok) return;
        const s = r.data;
        s.docks.forEach((d) => { const o = dockSel.querySelector(`option[value="${d.id}"]`); if (o) o.textContent = d.label; });
        document.getElementById('dockAlert').classList.toggle('d-none', !s.all_full);
        if (s.all_full) { dockHint.innerHTML = '<span class="text-danger">Semua dock penuh.</span>'; return; }
        if (s.current_full && autoSwitch && s.suggested) {
            dockSel.value = s.suggested.id;
            localStorage.setItem('rcvDock', s.suggested.id);
            dockHint.innerHTML = `<span class="text-primary">Dock sebelumnya penuh, disarankan ${s.suggested.code}.</span>`;
        } else if (s.current_full) {
            dockHint.innerHTML = `<span class="text-warning">Dock ini penuh untuk pallet baru. Saran: ${s.suggested?.code ?? '-'}.</span>`;
        } else {
            dockHint.textContent = '';
        }
    }
    dockSel.addEventListener('change', (e) => { localStorage.setItem('rcvDock', e.target.value); refreshDocks(false); });
    document.getElementById('btnNotifyDock').addEventListener('click', async (e) => {
        e.currentTarget.disabled = true;
        const r = await WMS.api(notifyUrl, { method: 'POST', body: {} });
        alert(r.data.message);
    });

    // Setiap kali modal dibuka: idempotency key baru, dock terakhir diingat lalu dicek kapasitasnya
    modal.addEventListener('wms:fill', () => {
        form.querySelector('[name=idempotency_key]').value = WMS.uuid();
        const lastDock = localStorage.getItem('rcvDock');
        if (lastDock && dockSel.querySelector(`option[value="${lastDock}"]`)) dockSel.value = lastDock;
        applyItem(itemSel.value);
        refreshDocks(!form.querySelector('[name=lpn_no]').value);
        setTimeout(() => document.getElementById('rcvScan').focus(), 300);
    });
})();
</script>
@endif
@endpush
