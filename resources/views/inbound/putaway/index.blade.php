@extends('layouts.app')
@section('title', 'Putaway')

@use('App\Support\Qty')
@use('Carbon\CarbonInterface')
@section('content')
@php
    $canDo = auth()->user()->can('access', ['INB_PUTAWAY', 'create']);
    $age = fn ($t) => \Carbon\Carbon::parse($t)->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, true, 2);
@endphp

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">Putaway</span></div>
        <div class="btn-group btn-group-sm o-segment">
            <a href="{{ request()->fullUrlWithQuery(['tab' => null, 'page' => null]) }}" class="btn btn-secondary {{ $tab === 'pending' ? 'active' : '' }}">Menunggu <span class="badge rounded-pill bg-primary ms-1">{{ $pending->count() }}</span></a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'history', 'page' => null]) }}" class="btn btn-secondary {{ $tab === 'history' ? 'active' : '' }}">Riwayat</a>
        </div>
    </div>
    @if ($canDo)
        <form class="o-scan-input" id="formScanLpn" autocomplete="off">
            <i class="bi bi-upc-scan"></i>
            <input class="form-control form-control-sm font-monospace" name="lpn" placeholder="Scan LPN untuk putaway" aria-label="Scan LPN">
        </form>
    @endif
    <a href="{{ route('m.putaway') }}" class="btn btn-sm btn-secondary"><i class="bi bi-phone me-1"></i>Mobile</a>
    @include('partials.searchbar', ['placeholder' => $tab === 'history' ? 'Cari no. task / LPN...' : 'Cari LPN, SKU, dock...', 'filters' => [
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
    ]])
    @if ($history)@include('partials.pager', ['paginator' => $history])@endif
</div>

@if ($tab === 'pending')
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>LPN</th><th>Dock</th><th>Isi</th><th class="text-end">Baris</th><th class="text-end">Qty (base)</th><th>Di dock sejak</th><th>Status</th>@if ($canDo)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($pending as $p)
            <tr>
                <td class="fw-semibold font-monospace text-nowrap">{{ $p->lpn_no }}</td>
                <td class="text-nowrap">{{ $p->location_code }}</td>
                <td>{{ $p->skus }}</td>
                <td class="text-end">{{ $p->line_count }}</td>
                <td class="text-end">{{ Qty::fmt($p->qty) }}</td>
                <td class="text-nowrap">{{ $p->received_at ? \Carbon\Carbon::parse($p->received_at)->format('d/m H:i') : '-' }}
                    @if ($p->received_at)<span class="badge rounded-pill {{ \Carbon\Carbon::parse($p->received_at)->lt(now()->subHours(4)) ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $age($p->received_at) }}</span>@endif
                </td>
                <td>
                    @if ($p->task)
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Dikerjakan</span>
                        <div class="small text-muted">{{ $p->task->creator?->name }} → {{ $p->task->suggestedLocation?->code ?? '?' }}</div>
                    @else
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">Menunggu</span>
                    @endif
                </td>
                @if ($canDo)
                    <td class="text-end"><button type="button" class="btn btn-sm btn-primary js-putaway" data-lpn="{{ $p->lpn_no }}"><i class="bi bi-box-arrow-in-right me-1"></i>Putaway</button></td>
                @endif
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-check2-circle"></i>Tidak ada pallet di dock. Semua barang sudah disimpan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@else
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. Task</th><th>LPN</th><th>Dari</th><th>Ke</th><th>Saran</th><th class="text-end">Qty (base)</th><th>Status</th><th>Selesai</th><th>Oleh</th></tr></thead>
        <tbody>
        @forelse ($history as $t)
            <tr>
                <td class="fw-semibold text-nowrap">{{ $t->task_no }}</td>
                <td class="font-monospace text-nowrap">{{ $t->lpn?->lpn_no }}</td>
                <td>{{ $t->fromLocation->code }}</td>
                <td class="fw-semibold">{{ $t->toLocation?->code ?? '-' }}</td>
                <td>{{ $t->suggestedLocation?->code ?? '-' }}
                    @if ($t->toLocation && $t->suggested_location_id && $t->to_location_id !== $t->suggested_location_id)<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">beda</span>@endif
                </td>
                <td class="text-end">{{ $t->qty_sum ? Qty::fmt($t->qty_sum) : '-' }}</td>
                <td><span class="badge rounded-pill bg-{{ $t->status->color() }}-subtle text-{{ $t->status->color() }}-emphasis">{{ $t->status->label() }}</span></td>
                <td class="text-nowrap">{{ $t->completed_at?->format('d/m H:i') ?? '-' }}</td>
                <td class="small">{{ $t->completer?->name ?? $t->creator?->name }}<div class="text-muted">{{ $t->source }}</div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="o-empty"><i class="bi bi-clock-history"></i>Belum ada riwayat putaway.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection

@if ($canDo)
@push('modals')
<div class="modal fade" id="modalPutaway" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content o-input">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-right me-1"></i> Putaway <span class="font-monospace" id="ptLpn"></span></h5>
                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis ms-2" id="ptTask"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small fw-semibold text-muted mb-1">Isi pallet · dari <span id="ptFrom"></span></div>
                        <div class="o-pt-contents" id="ptContents"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="o-pt-suggest" id="ptSuggest"></div>
                        <div class="small fw-semibold text-muted mt-3 mb-1">Alternatif</div>
                        <div class="d-flex flex-wrap gap-1" id="ptAlt"></div>
                        <div class="small fw-semibold text-muted mt-3 mb-1">Atau scan / ketik lokasi lain</div>
                        <form class="input-group input-group-sm" id="ptLocForm" autocomplete="off">
                            <input class="form-control font-monospace text-uppercase" id="ptLocInput" placeholder="mis. A-01-03-2">
                            <button class="btn btn-secondary" type="submit">Pilih</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-danger me-auto" id="ptCancel">Batalkan task</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="ptSave" disabled><i class="bi bi-check2 me-1"></i>Simpan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
(() => {
    const urls = {
        start: @json(route('inbound.putaway.start')),
        base: @json(url('inbound/putaway/tasks')),
    };
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const modalEl = $('modalPutaway');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const alertBox = modalEl.querySelector('.form-alert');
    let task = null;
    let target = null; // {id, code}

    function error(msg) { alertBox.textContent = msg || ''; alertBox.classList.toggle('d-none', !msg); }

    async function open(lpn) {
        const r = await WMS.api(urls.start, { method: 'POST', body: { lpn_no: lpn } });
        if (!r.ok) return WMS.showToast(r.data.message, 'danger');
        render(r.data);
        modal.show();
    }

    function render(t) {
        task = t;
        error('');
        $('ptLpn').textContent = t.lpn_no;
        $('ptTask').textContent = t.task_no;
        $('ptFrom').textContent = t.from;
        $('ptContents').innerHTML = t.contents.map((c) => `
            <div class="o-pt-line"><div class="min-w-0"><b>${esc(c.sku)}</b> <span class="text-muted small">${esc(c.name)}</span>
            <div class="small text-muted">${c.lot_no ? 'Lot ' + esc(c.lot_no) : ''}${c.expiry_date ? ' · Exp ' + esc(c.expiry_date) : ''} · ${esc(c.status)}</div></div>
            <div class="fw-semibold text-nowrap">${esc(c.qty)} ${esc(c.uom)}</div></div>`).join('');
        $('ptSuggest').innerHTML = t.suggested
            ? `<div class="small fw-semibold opacity-75">Lokasi saran</div><div class="o-pt-code">${esc(t.suggested.code)}</div><div class="small">${esc(t.suggested.reason)}</div>`
            : `<div class="small fw-semibold">Tidak ada lokasi yang cocok</div><div class="small">${esc(t.suggest_reason)}</div>`;
        $('ptSuggest').classList.toggle('is-empty', !t.suggested);
        $('ptAlt').innerHTML = t.alternatives.map((a) => `<button type="button" class="o-dock-chip" data-id="${a.id}" data-code="${esc(a.code)}" title="${esc(a.reason)}">${esc(a.code)} <b>${esc(a.cap)}</b></button>`).join('')
            || '<span class="small text-muted">-</span>';
        choose(t.suggested ? { id: t.suggested.id, code: t.suggested.code } : null);
    }

    function choose(loc) {
        target = loc;
        const save = $('ptSave');
        save.disabled = !loc;
        save.innerHTML = `<i class="bi bi-check2 me-1"></i>Simpan ke ${loc ? esc(loc.code) : '...'}`;
        modalEl.querySelectorAll('#ptAlt .o-dock-chip').forEach((b) => b.classList.toggle('active', loc && Number(b.dataset.id) === loc.id));
        $('ptSuggest').classList.toggle('is-chosen', !!(loc && task.suggested && loc.id === task.suggested.id));
    }

    $('ptSuggest').addEventListener('click', () => task?.suggested && choose({ id: task.suggested.id, code: task.suggested.code }));
    $('ptAlt').addEventListener('click', (e) => { const b = e.target.closest('[data-id]'); if (b) choose({ id: Number(b.dataset.id), code: b.dataset.code }); });
    $('ptLocForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = $('ptLocInput').value.trim();
        if (!code) return;
        const r = await WMS.api(`${urls.base}/${task.id}/resolve-location`, { params: { code } });
        if (!r.ok) return error(r.data.message);
        error('');
        choose({ id: r.data.id, code: r.data.code });
        $('ptLocInput').value = '';
    });

    async function save(confirmFlag = false) {
        const btn = $('ptSave');
        btn.disabled = true;
        const r = await WMS.api(`${urls.base}/${task.id}/complete`, { method: 'POST', body: { location_id: target.id, confirm: confirmFlag ? 1 : 0 } });
        btn.disabled = false;
        if (r.status === 409 && r.data.confirm) {
            const text = r.data.warnings.join('\n');
            if (!r.data.can_confirm) return error(`${text} Minta persetujuan SPV.`);
            if (confirm(`${text}\n\nTetap simpan?`)) return save(true);
            return;
        }
        if (!r.ok) return error(r.data.message);
        location.reload();
    }
    $('ptSave').addEventListener('click', () => target && save());
    $('ptCancel').addEventListener('click', async () => {
        if (!confirm(`Batalkan task ${task.task_no}? Stok tetap di dock.`)) return;
        const r = await WMS.api(`${urls.base}/${task.id}/cancel`, { method: 'POST', body: {} });
        if (!r.ok) return error(r.data.message);
        location.reload();
    });

    document.querySelectorAll('.js-putaway').forEach((b) => b.addEventListener('click', () => open(b.dataset.lpn)));
    $('formScanLpn')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const v = e.target.lpn.value.trim();
        if (v) { open(v); e.target.lpn.value = ''; }
    });
    modalEl.addEventListener('shown.bs.modal', () => $('ptLocInput').focus());
})();
</script>
@endpush
@endif
