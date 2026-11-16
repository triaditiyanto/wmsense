@extends('layouts.app')
@section('title', $title)

@use('App\Support\Qty')
@section('content')
@php($canDo = auth()->user()->can('access', [$code, 'create']))

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">{{ $title }}</span></div>
        <div class="btn-group btn-group-sm o-segment">
            <a href="{{ request()->fullUrlWithQuery(['tab' => null]) }}" class="btn btn-secondary {{ $tab === 'stock' ? 'active' : '' }}">Stok</a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'history']) }}" class="btn btn-secondary {{ $tab === 'history' ? 'active' : '' }}">Riwayat</a>
        </div>
    </div>
    @if ($mode === 'adjust' && $canDo)
        <button type="button" class="btn btn-sm btn-primary" id="btnNew"><i class="bi bi-plus-lg me-1"></i>Stok temuan</button>
    @endif
    @include('partials.searchbar', ['placeholder' => 'Cari lokasi / LPN / SKU / lot...', 'filters' => ['warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()]]])
</div>

@if ($tab === 'stock')
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Lokasi</th><th>LPN</th><th>Item</th><th>Lot / Exp</th><th>Status</th><th class="text-end">On hand</th><th class="text-end">Alokasi</th><th class="text-end">Hold</th><th class="text-end">Tersedia</th>@if ($canDo)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr data-id="{{ $r->id }}" data-sku="{{ $r->item->sku }}" data-name="{{ $r->item->name }}" data-loc="{{ $r->location->code }}" data-lpn="{{ $r->lpn?->lpn_no }}"
                data-onhand="{{ rtrim(rtrim($r->qty_on_hand, '0'), '.') }}" data-avail="{{ rtrim(rtrim($r->qty_available, '0'), '.') }}" data-hold="{{ rtrim(rtrim($r->qty_hold, '0'), '.') ?: 0 }}" data-uom="{{ $r->item->baseUom->code }}">
                <td class="fw-semibold">{{ $r->location->code }}</td>
                <td class="font-monospace small">{{ $r->lpn?->lpn_no ?? '-' }}</td>
                <td><b>{{ $r->item->sku }}</b> <span class="small text-muted">{{ \Illuminate\Support\Str::limit($r->item->name, 30) }}</span></td>
                <td class="small">{{ $r->lot_no ?? '-' }}@if ($r->expiry_date)<div>Exp {{ $r->expiry_date->format('d/m/Y') }}</div>@endif</td>
                <td>@if ($r->status->value !== 'AVAILABLE')<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">{{ $r->status->label() }}</span>@else<span class="text-muted small">Tersedia</span>@endif</td>
                <td class="text-end">{{ Qty::fmt($r->qty_on_hand) }} <span class="small text-muted">{{ $r->item->baseUom->code }}</span></td>
                <td class="text-end">{{ Qty::cmp($r->qty_allocated, 0) > 0 ? Qty::fmt($r->qty_allocated) : '-' }}</td>
                <td class="text-end {{ Qty::cmp($r->qty_hold, 0) > 0 ? 'text-warning fw-semibold' : '' }}">{{ Qty::cmp($r->qty_hold, 0) > 0 ? Qty::fmt($r->qty_hold) : '-' }}</td>
                <td class="text-end">{{ Qty::fmt($r->qty_available) }}</td>
                @if ($canDo)
                <td class="text-end text-nowrap">
                    @if ($mode === 'move')<button type="button" class="btn btn-sm btn-primary js-act"><i class="bi bi-arrow-left-right me-1"></i>Pindah</button>
                    @elseif ($mode === 'adjust')<button type="button" class="btn btn-sm btn-primary js-act"><i class="bi bi-pencil-square me-1"></i>Koreksi</button>
                    @else
                        <button type="button" class="btn btn-sm btn-warning js-act" data-op="hold" {{ Qty::cmp($r->qty_available, 0) > 0 ? '' : 'disabled' }}><i class="bi bi-pause-circle me-1"></i>Hold</button>
                        <button type="button" class="btn btn-sm btn-secondary js-act" data-op="release" {{ Qty::cmp($r->qty_hold, 0) > 0 ? '' : 'disabled' }}><i class="bi bi-play-circle me-1"></i>Release</button>
                    @endif
                </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="10" class="o-empty"><i class="bi bi-search"></i>Tidak ada stok. Cari dengan kode lokasi, LPN, SKU atau lot.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@else
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Waktu</th><th>Transaksi</th><th>Lokasi</th><th>Item</th><th>LPN</th><th class="text-end">Perubahan</th><th>Alasan</th><th>Oleh</th></tr></thead>
        <tbody>
        @forelse ($history as $m)
            @php($chg = $mode === 'hold' ? $m->qty_hold_change : $m->qty_on_hand_change)
            <tr>
                <td class="text-nowrap small">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $m->type->label() }}</td>
                <td class="fw-semibold">{{ $m->location->code }}</td>
                <td><b>{{ $m->item->sku }}</b></td>
                <td class="font-monospace small">{{ $m->lpn?->lpn_no ?? '-' }}</td>
                <td class="text-end fw-semibold {{ Qty::cmp($chg, 0) < 0 ? 'text-danger' : 'text-success' }}">{{ Qty::cmp($chg, 0) > 0 ? '+' : '' }}{{ Qty::fmt($chg) }}</td>
                <td class="small">{{ $m->reason_code }}{{ $m->notes ? ' · '.$m->notes : '' }}</td>
                <td class="small text-muted">{{ $m->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-clock-history"></i>Belum ada riwayat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection

@if ($canDo)
@push('modals')
<div class="modal fade" id="modalAct" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content o-input">
            <div class="modal-header"><h5 class="modal-title" id="actTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="small mb-3" id="actInfo"></div>
                <div id="fMove" class="d-none">
                    <div class="mb-3"><label class="form-label">Lokasi tujuan</label><input class="form-control font-monospace text-uppercase" id="inToCode" autocomplete="off" placeholder="mis. A-02-03-1"></div>
                    <div class="mb-3" id="boxMoveQty"><label class="form-label">Qty dipindah</label><input type="number" class="form-control" id="inMoveQty" min="0" step="any"><div class="form-text">Kosong = semua stok bebas. Stok ber-LPN dipindah utuh bersama LPN-nya.</div></div>
                </div>
                <div id="fAdjust" class="d-none">
                    <div class="mb-3"><label class="form-label">Qty hasil hitung</label><input type="number" class="form-control" id="inNewQty" min="0" step="any"></div>
                </div>
                <div id="fHold" class="d-none">
                    <div class="mb-3"><label class="form-label">Qty</label><input type="number" class="form-control" id="inHoldQty" min="0" step="any"></div>
                </div>
                <div id="fNew" class="d-none">
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Lokasi</label><input class="form-control font-monospace text-uppercase" id="nLoc"></div>
                        <div class="col-6"><label class="form-label">SKU / barcode</label><input class="form-control font-monospace" id="nSku"></div>
                        <div class="col-4"><label class="form-label">Qty</label><input type="number" class="form-control" id="nQty" min="0" step="any"></div>
                        <div class="col-4"><label class="form-label">Lot</label><input class="form-control" id="nLot"></div>
                        <div class="col-4"><label class="form-label">Expired</label><input type="date" class="form-control" id="nExp"></div>
                    </div>
                </div>
                <div class="mb-3 d-none" id="boxReason"><label class="form-label">Alasan</label><select class="form-select" id="inReason"></select></div>
                <div class="mb-0"><label class="form-label">Catatan</label><input class="form-control" id="inNotes" maxlength="255"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="actSave"><i class="bi bi-check2 me-1"></i>Simpan</button></div>
        </div>
    </div>
</div>
@endpush
@push('scripts')
<script>
(() => {
    const MODE = @json($mode); const WH = @json($warehouseId);
    const urls = { move: @json(route('inventory.move.store')), adjust: @json(route('inventory.adjust.store')), adjustNew: @json(route('inventory.adjust.new')), hold: @json(route('inventory.hold.store')), release: @json(route('inventory.hold.release')) };
    const reasons = { adjust: @json($adjustReasons), hold: @json($holdReasons) };
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const modalEl = $('modalAct'); const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const alertBox = modalEl.querySelector('.form-alert');
    const error = (m) => { alertBox.textContent = m || ''; alertBox.classList.toggle('d-none', !m); };
    let row = null; let op = MODE;
    const show = (ids) => ['fMove', 'fAdjust', 'fHold', 'fNew', 'boxReason'].forEach((i) => $(i).classList.toggle('d-none', !ids.includes(i)));
    const fillReasons = (map) => { $('inReason').innerHTML = Object.entries(map).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join(''); };

    function open(tr, which) {
        row = { ...tr.dataset }; op = which || MODE; error(''); $('inNotes').value = '';
        $('actInfo').innerHTML = `<b>${esc(row.sku)}</b> ${esc(row.name)}<br>Lokasi <b>${esc(row.loc)}</b>${row.lpn ? ` · LPN <b>${esc(row.lpn)}</b>` : ''} · on hand <b>${esc(row.onhand)} ${esc(row.uom)}</b>, tersedia ${esc(row.avail)}, hold ${esc(row.hold)}`;
        if (op === 'move') {
            $('actTitle').textContent = 'Pindah stok'; show(['fMove']); $('inToCode').value = ''; $('inMoveQty').value = '';
            $('boxMoveQty').classList.toggle('d-none', !!row.lpn);
        } else if (op === 'adjust') {
            $('actTitle').textContent = 'Koreksi stok'; show(['fAdjust', 'boxReason']); fillReasons(reasons.adjust); $('inNewQty').value = row.onhand;
        } else if (op === 'hold') {
            $('actTitle').textContent = 'Hold stok'; show(['fHold', 'boxReason']); fillReasons(reasons.hold); $('inHoldQty').value = row.avail;
        } else {
            $('actTitle').textContent = 'Release hold'; show(['fHold']); $('inHoldQty').value = row.hold;
        }
        modal.show();
    }
    document.querySelectorAll('.js-act').forEach((b) => b.addEventListener('click', () => open(b.closest('tr'), b.dataset.op)));
    $('btnNew')?.addEventListener('click', () => {
        op = 'new'; error(''); $('inNotes').value = ''; $('actTitle').textContent = 'Tambah stok temuan'; $('actInfo').textContent = 'Stok lepas (tanpa LPN), status Tersedia.';
        show(['fNew', 'boxReason']); fillReasons(reasons.adjust); $('inReason').value = 'FOUND'; modal.show();
    });

    async function save(confirmFlag = false) {
        const notes = $('inNotes').value || null; const reason = $('inReason').value; let url; let body;
        if (op === 'move') { url = urls.move; body = { inventory_id: row.id, warehouse_id: WH, to_code: $('inToCode').value, qty: row.lpn ? null : ($('inMoveQty').value || null), confirm: confirmFlag ? 1 : 0, notes }; }
        else if (op === 'adjust') { url = urls.adjust; body = { inventory_id: row.id, new_qty: $('inNewQty').value, reason, notes }; }
        else if (op === 'hold') { url = urls.hold; body = { inventory_id: row.id, qty: $('inHoldQty').value, reason, notes }; }
        else if (op === 'release') { url = urls.release; body = { inventory_id: row.id, qty: $('inHoldQty').value, notes }; }
        else { url = urls.adjustNew; body = { warehouse_id: WH, location_code: $('nLoc').value, sku: $('nSku').value, qty: $('nQty').value, lot_no: $('nLot').value || null, expiry_date: $('nExp').value || null, reason, notes }; }
        const btn = $('actSave'); btn.disabled = true;
        const r = await WMS.api(url, { method: 'POST', body }); btn.disabled = false;
        if (r.status === 409 && r.data.confirm) {
            const text = r.data.warnings.join('\n');
            if (!r.data.can_confirm) return error(`${text} Butuh persetujuan SPV.`);
            if (confirm(`${text}\n\nTetap lanjutkan?`)) return save(true);
            return;
        }
        if (!r.ok) return error(r.data.message || Object.values(r.data.errors || {}).flat().join(' '));
        sessionStorage.setItem('wmsFlash', r.data.message); location.reload();
    }
    $('actSave').addEventListener('click', () => save());
    try { const f = sessionStorage.getItem('wmsFlash'); if (f) { sessionStorage.removeItem('wmsFlash'); WMS.showToast(f); } } catch (e) {}
})();
</script>
@endpush
@endif
