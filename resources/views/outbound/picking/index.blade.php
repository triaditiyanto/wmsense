@extends('layouts.app')
@section('title', 'Picking')

@use('App\Support\Qty')
@section('content')
@php($canDo = auth()->user()->can('access', ['OUT_PICKING', 'create']))

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">Picking</span></div>
        <div class="btn-group btn-group-sm o-segment">
            <a href="{{ request()->fullUrlWithQuery(['tab' => null, 'page' => null]) }}" class="btn btn-secondary {{ $tab === 'queue' ? 'active' : '' }}">Antrian <span class="badge rounded-pill bg-primary ms-1">{{ $queue->count() }}</span></a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'history', 'page' => null]) }}" class="btn btn-secondary {{ $tab === 'history' ? 'active' : '' }}">Riwayat</a>
        </div>
    </div>
    @if ($canDo)
        <form class="o-scan-input" id="formScanWave" autocomplete="off">
            <i class="bi bi-upc-scan"></i>
            <input class="form-control form-control-sm font-monospace" name="wave" placeholder="Scan / ketik no. wave" aria-label="Scan wave">
        </form>
    @endif
    <a href="{{ route('m.picking') }}" class="btn btn-sm btn-secondary"><i class="bi bi-phone me-1"></i>Mobile</a>
    @include('partials.searchbar', ['placeholder' => 'Cari no. wave / shipment / customer...', 'filters' => ['warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()]]])
    @if ($history)@include('partials.pager', ['paginator' => $history])@endif
</div>

@if ($tab === 'queue')
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. Wave</th><th>Jenis</th><th>Shipment</th><th class="text-end">Baris</th><th>Progres</th><th>Staging</th><th>Dibuat</th><th>Status</th>@if ($canDo)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($queue as $w)
            @php($total = $w->open_count + $w->done_count)
            <tr>
                <td class="o-td-title fw-semibold text-nowrap">{{ $w->wave_no }}</td>
                <td>{{ $w->typeLabel() }}</td>
                <td class="small">@foreach ($w->shipments as $s)<div>{{ $s->shipment_no }} <span class="text-muted">· {{ \Illuminate\Support\Str::limit($s->ship_to_name ?? $s->customer->name, 28) }}</span></div>@endforeach</td>
                <td class="text-end">{{ $total }}</td>
                <td style="min-width:140px">
                    <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $total ? round($w->done_count / $total * 100) : 0 }}%"></div></div>
                    <div class="small text-muted">{{ $w->done_count }} / {{ $total }} baris</div>
                </td>
                <td>{{ $w->stagingLocation?->code ?? '-' }}</td>
                <td class="text-nowrap small">{{ $w->created_at->format('d/m H:i') }}</td>
                <td><span class="badge rounded-pill bg-{{ $w->status->color() }}-subtle text-{{ $w->status->color() }}-emphasis">{{ $w->status->label() }}</span></td>
                @if ($canDo)<td class="text-end"><button type="button" class="btn btn-sm btn-primary js-pick" data-id="{{ $w->id }}"><i class="bi bi-cart-check me-1"></i>{{ $w->status->value === 'PICKING' ? 'Lanjutkan' : 'Mulai' }}</button></td>@endif
            </tr>
        @empty
            <tr><td colspan="9" class="o-empty"><i class="bi bi-check2-circle"></i>Tidak ada wave yang menunggu dipick. Jalankan <b>Run Wave</b> di Wave Planning.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@else
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. Wave</th><th>Shipment</th><th class="text-end">Baris</th><th class="text-end">Kurang</th><th class="text-end">Qty dipick</th><th>Staging</th><th>Selesai</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($history as $w)
            <tr class="o-row-link" data-href="{{ route('outbound.waves.show', $w) }}">
                <td class="fw-semibold text-nowrap">{{ $w->wave_no }}</td>
                <td class="small">{{ $w->shipments->pluck('shipment_no')->implode(', ') }}</td>
                <td class="text-end">{{ $w->done_count }}</td>
                <td class="text-end">{{ $w->short_count ?: '-' }}</td>
                <td class="text-end">{{ Qty::fmt($w->picked_sum ?? 0) }}</td>
                <td>{{ $w->stagingLocation?->code ?? '-' }}</td>
                <td class="text-nowrap">{{ $w->picked_at?->format('d/m H:i') ?? '-' }}</td>
                <td><span class="badge rounded-pill bg-{{ $w->status->color() }}-subtle text-{{ $w->status->color() }}-emphasis">{{ $w->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-clock-history"></i>Belum ada riwayat picking.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection

@if ($canDo)
@push('modals')
<div class="modal fade" id="modalPick" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content o-input">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cart-check me-1"></i> Picking <span id="pkNo"></span></h5>
                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis ms-2" id="pkStatus"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="small" id="pkShip"></div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <label class="small text-muted text-nowrap">Staging tujuan</label>
                        <select class="form-select form-select-sm" id="pkStaging" style="min-width:150px"></select>
                    </div>
                </div>
                <div class="progress mb-3" style="height:8px"><div class="progress-bar" id="pkBar" style="width:0%"></div></div>
                <div class="o-list table-responsive">
                    <table class="table table-sm table-hover mb-0" id="pkTable">
                        <thead><tr><th>#</th><th>Lokasi</th><th>Item</th><th>Lot / Exp</th><th>LPN</th><th>Shipment</th><th class="text-end">Qty</th><th style="width:130px">Dipick</th><th></th></tr></thead>
                        <tbody id="pkBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" id="pkProgress"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="pkFinish"><i class="bi bi-check2-all me-1"></i>Selesai picking</button>
            </div>
        </div>
    </div>
</div>
@endpush
@push('scripts')
<script>
(() => {
    const urls = { find: @json(route('outbound.picking.find')), waves: @json(url('outbound/picking/waves')), lines: @json(url('outbound/picking/lines')) };
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const modalEl = $('modalPick'); const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const alertBox = modalEl.querySelector('.form-alert');
    let wave = null; let changed = false;
    const error = (msg) => { alertBox.textContent = msg || ''; alertBox.classList.toggle('d-none', !msg); };

    async function open(id) {
        const r = await WMS.api(`${urls.waves}/${id}`);
        if (!r.ok) return WMS.showToast(r.data.message, 'danger');
        render(r.data); modal.show();
    }
    function render(w) {
        wave = w; error('');
        $('pkNo').textContent = w.wave_no; $('pkStatus').textContent = w.status_label;
        $('pkShip').innerHTML = w.shipments.map((s) => `<span class="o-dock-chip me-1">${esc(s.no)} <b>${esc(s.customer).slice(0, 18)}</b></span>`).join('');
        const st = $('pkStaging'); st.innerHTML = w.stagings.map((s) => `<option value="${s.id}" ${w.staging && w.staging.id === s.id ? 'selected' : ''}>${esc(s.code)}</option>`).join('') || '<option value="">(belum ada staging)</option>';
        $('pkBar').style.width = `${w.progress.total ? Math.round(w.progress.done / w.progress.total * 100) : 0}%`;
        $('pkProgress').textContent = `${w.progress.done} / ${w.progress.total} baris selesai${w.progress.short ? `, ${w.progress.short} kurang` : ''}`;
        $('pkFinish').disabled = w.status === 'PICKED';
        $('pkBody').innerHTML = w.lines.map((l) => `
            <tr class="${l.status === 'OPEN' ? '' : 'table-light text-muted'}" data-id="${l.id}">
                <td>${l.seq}</td><td class="fw-semibold">${esc(l.location)}</td>
                <td><b>${esc(l.sku)}</b> <span class="small">${esc(l.name)}</span></td>
                <td class="small">${esc(l.lot_no || '-')}${l.expiry_date ? `<div>Exp ${esc(l.expiry_date)}</div>` : ''}</td>
                <td class="font-monospace small">${esc(l.lpn_no || '-')}</td>
                <td class="small">${esc(l.shipment_no)}</td>
                <td class="text-end text-nowrap">${esc(l.qty)} ${esc(l.uom)}</td>
                <td>${l.status === 'OPEN' ? `<input type="number" class="form-control form-control-sm js-qty" value="${esc(l.qty_raw)}" min="0" max="${esc(l.qty_raw)}" step="any">` : `<span class="badge rounded-pill bg-${l.color}-subtle text-${l.color}-emphasis">${esc(l.status_label)}</span> ${esc(l.qty_picked)}${l.short_reason ? `<div class="small">${esc(l.short_reason)}</div>` : ''}`}</td>
                <td class="text-end">${l.status === 'OPEN' ? `<button type="button" class="btn btn-sm btn-primary js-do"><i class="bi bi-check2"></i> Pick</button>` : `<span class="small text-muted">${esc(l.picked_by || '')}</span>`}</td>
            </tr>`).join('') || '<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada baris.</td></tr>';
    }
    $('pkBody').addEventListener('click', async (e) => {
        const b = e.target.closest('.js-do'); if (!b) return;
        const tr = b.closest('tr'); const qty = tr.querySelector('.js-qty').value;
        await pick(tr.dataset.id, qty, b);
    });
    $('pkBody').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('.js-qty')) { e.preventDefault(); e.target.closest('tr').querySelector('.js-do').click(); } });
    async function pick(id, qty, btn, confirmFlag = false) {
        btn.disabled = true;
        const r = await WMS.api(`${urls.lines}/${id}/pick`, { method: 'POST', body: { qty, staging_location_id: $('pkStaging').value || null, confirm: confirmFlag ? 1 : 0, idempotency_key: WMS.uuid(), source: 'DESKTOP' } });
        btn.disabled = false;
        if (r.status === 409 && r.data.confirm) {
            const text = r.data.warnings.join('\n');
            if (!r.data.can_confirm) return error(`${text} Butuh persetujuan SPV.`);
            if (confirm(`${text}\n\nTetap lanjutkan?`)) return pick(id, qty, btn, true);
            return;
        }
        if (!r.ok) return error(r.data.message);
        changed = true; WMS.showToast(r.data.message);
        const w = await WMS.api(`${urls.waves}/${wave.id}`); if (w.ok) render(w.data);
    }
    $('pkFinish').addEventListener('click', async function finish(confirmFlag = false) {
        const r = await WMS.api(`${urls.waves}/${wave.id}/finish`, { method: 'POST', body: { confirm: confirmFlag === true ? 1 : 0 } });
        if (r.status === 409 && r.data.confirm) {
            const text = r.data.warnings.join('\n');
            if (!r.data.can_confirm) return error(`${text} Butuh persetujuan SPV.`);
            if (confirm(`${text}\n\nTetap selesaikan?`)) return finish(true);
            return;
        }
        if (!r.ok) return error(r.data.message);
        location.reload();
    });
    modalEl.addEventListener('hidden.bs.modal', () => { if (changed) location.reload(); });
    document.querySelectorAll('.js-pick').forEach((b) => b.addEventListener('click', () => open(b.dataset.id)));
    $('formScanWave')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const v = e.target.wave.value.trim(); if (!v) return;
        const r = await WMS.api(urls.find, { method: 'POST', body: { wave_no: v } });
        if (!r.ok) return WMS.showToast(r.data.message, 'danger');
        e.target.wave.value = ''; render(r.data); modal.show();
    });
})();
</script>
@endpush
@endif
