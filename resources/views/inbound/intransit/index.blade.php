@extends('layouts.app')
@section('title', 'Intransit In')

@use('App\Support\Qty')
@use('Carbon\CarbonInterface')
@section('content')
@php
    $canCancel = auth()->user()->can('access', ['INB_INTRANSIT', 'delete']);
    $oldest = $summary->oldest ? \Carbon\Carbon::parse($summary->oldest) : null;
    $age = fn ($t) => $t->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, true, 2);
@endphp

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">Intransit In</span></div>
        <span class="text-muted small d-none d-md-inline">Sudah diterima, belum disimpan ke rak</span>
    </div>
    @if ($canCancel)
        <button type="button" class="btn btn-sm btn-danger d-none" id="btnBulkCancel"><i class="bi bi-arrow-counterclockwise me-1"></i>Batal terima (<span>0</span>)</button>
    @endif
    @include('partials.searchbar', ['placeholder' => 'Cari dokumen, LPN, SKU, lot...', 'filters' => [
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        'location' => ['Dock', $docks->pluck('code', 'id')->all()],
        'condition' => ['Kondisi', collect($conditions)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()],
        'age' => ['Umur', ['stale' => "Lebih dari {$staleHours} jam"]],
    ]])
    @include('partials.pager', ['paginator' => $lines])
</div>

{{-- Ringkasan --}}
<div class="o-kpis">
    <div class="o-kpi"><div class="o-kpi-value">{{ number_format($summary->lpn_count) }}</div><div class="o-kpi-label"><i class="bi bi-stack me-1"></i>Pallet (LPN) di dock</div></div>
    <div class="o-kpi"><div class="o-kpi-value">{{ number_format($summary->line_count) }}</div><div class="o-kpi-label"><i class="bi bi-list-ul me-1"></i>Baris barang</div></div>
    <div class="o-kpi"><div class="o-kpi-value">{{ number_format($summary->receipt_count) }}</div><div class="o-kpi-label"><i class="bi bi-file-earmark-text me-1"></i>Dokumen</div></div>
    <div class="o-kpi"><div class="o-kpi-value">{{ $oldest ? $age($oldest) : '-' }}</div><div class="o-kpi-label"><i class="bi bi-hourglass-split me-1"></i>Terlama di dock</div></div>
    <a class="o-kpi {{ $summary->stale_count ? 'is-warning' : '' }}" href="{{ request()->fullUrlWithQuery(['age' => 'stale', 'page' => null]) }}">
        <div class="o-kpi-value">{{ number_format($summary->stale_count) }}</div>
        <div class="o-kpi-label"><i class="bi bi-exclamation-triangle me-1"></i>Lebih dari {{ $staleHours }} jam</div>
    </a>
</div>

{{-- Kartu per dock: klik untuk filter --}}
@if ($docks->isNotEmpty())
    <div class="o-dock-strip">
        <a href="{{ request()->fullUrlWithQuery(['location' => null, 'page' => null]) }}" class="o-dock-chip {{ request()->filled('location') ? '' : 'active' }}">Semua dock</a>
        @foreach ($docks as $d)
            <a href="{{ request()->fullUrlWithQuery(['location' => $d->id, 'page' => null]) }}" class="o-dock-chip {{ (string) request('location') === (string) $d->id ? 'active' : '' }}">
                <i class="bi bi-geo-alt"></i>{{ $d->code }}<b>{{ $perDock[$d->id] ?? 0 }}</b>
            </a>
        @endforeach
    </div>
@endif

<div class="o-list table-responsive">
    <table class="table table-hover" id="itTable">
        <thead><tr>
            @if ($canCancel)<th style="width:36px"><input type="checkbox" class="form-check-input" id="chkAll" aria-label="Pilih semua"></th>@endif
            <th>Diterima</th><th>Dock</th><th>LPN</th><th>Barang</th><th>Lot / Expired</th><th class="text-end">Qty</th><th>Kondisi</th><th>Dokumen</th><th class="d-none d-xl-table-cell">Oleh</th>
            @if ($canCancel)<th></th>@endif
        </tr></thead>
        <tbody>
        @forelse ($lines as $l)
            @php($stale = $l->received_at->lt(now()->subHours($staleHours)))
            <tr data-id="{{ $l->id }}" data-summary="{{ $l->item->sku }} · {{ Qty::fmt($l->qty) }} {{ $l->uom->code }} · {{ $l->lpn->lpn_no }}">
                @if ($canCancel)<td><input type="checkbox" class="form-check-input js-chk" value="{{ $l->id }}" aria-label="Pilih"></td>@endif
                <td class="text-nowrap">
                    <div>{{ $l->received_at->format('d/m H:i') }}</div>
                    <span class="badge rounded-pill {{ $stale ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                        <i class="bi bi-clock"></i> {{ $age($l->received_at) }}
                    </span>
                </td>
                <td class="fw-semibold text-nowrap">{{ $l->location->code }}</td>
                <td class="text-nowrap"><a href="{{ request()->fullUrlWithQuery(['q' => $l->lpn->lpn_no, 'page' => null]) }}" class="font-monospace small text-decoration-none" title="Tampilkan isi pallet ini">{{ $l->lpn->lpn_no }}</a></td>
                <td class="o-td-title"><div class="fw-semibold">{{ $l->item->sku }}</div><div class="small text-muted">{{ $l->item->name }}</div></td>
                <td>
                    @if ($l->lot_no)<div>{{ $l->lot_no }}</div>@endif
                    @if ($l->expiry_date)<div class="small text-muted">Exp {{ $l->expiry_date->format('d/m/Y') }}</div>@endif
                    @if (! $l->lot_no && ! $l->expiry_date)<span class="text-muted">-</span>@endif
                </td>
                <td class="text-end text-nowrap"><span class="fw-semibold">{{ Qty::fmt($l->qty) }} {{ $l->uom->code }}</span>
                    @if ((float) $l->conversion_factor !== 1.0)<div class="small text-muted">{{ Qty::fmt($l->qty_base) }} base</div>@endif
                </td>
                <td><span class="badge rounded-pill bg-{{ $l->condition->color() }}-subtle text-{{ $l->condition->color() }}-emphasis">{{ $l->condition->label() }}</span></td>
                <td>
                    <a href="{{ route('inbound.receipts.show', $l->header) }}" class="text-decoration-none fw-semibold text-nowrap">{{ $l->header->receipt_no }}</a>
                    <div class="small text-muted">{{ $l->header->senderName() }}</div>
                </td>
                <td class="small d-none d-xl-table-cell">{{ $l->receivedBy?->name ?? '-' }}<div class="text-muted">{{ $l->source }}</div></td>
                @if ($canCancel)
                    <td class="text-end"><button type="button" class="btn btn-sm btn-secondary js-cancel-one" title="Batal terima"><i class="bi bi-arrow-counterclockwise"></i><span class="d-md-none ms-1">Batal terima</span></button></td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $canCancel ? 11 : 9 }}" class="o-empty"><i class="bi bi-truck"></i>Tidak ada barang di dock.<br>Semua barang yang diterima sudah disimpan, atau belum ada penerimaan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@if ($canCancel)
@push('modals')
<div class="modal fade" id="modalCancel" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content o-input" id="formCancel" novalidate>
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-1"></i> Batal terima</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-cancel-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>Stok di dock dikurangi dan qty <b>kembali ke Receiving</b> (bisa diterima ulang). Dokumen yang sudah <b>Received</b> dibuka ulang menjadi <b>Receiving</b>.</div>
                </div>
                <div class="small fw-semibold text-muted mt-3 mb-1"><span id="cancelCount">0</span> baris dipilih</div>
                <ul class="o-cancel-list" id="cancelList"></ul>
                <div class="o-field mt-3">
                    <label class="o-required">Alasan</label>
                    <div>
                    <div class="d-flex flex-wrap gap-1 mb-2" id="reasonChips">
                        @foreach (['Salah scan item', 'Qty salah hitung', 'Salah LPN / pallet', 'Ditolak QC', 'Barang dikembalikan ke supplier'] as $r)
                            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-reason="{{ $r }}">{{ $r }}</button>
                        @endforeach
                    </div>
                    <textarea name="reason" class="form-control" rows="2" maxlength="255" placeholder="Tulis alasan pembatalan"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Batalkan terima</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
(() => {
    const url = @json(route('inbound.intransit.cancel'));
    const chks = [...document.querySelectorAll('.js-chk')];
    const bulk = document.getElementById('btnBulkCancel');
    const modalEl = document.getElementById('modalCancel');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('formCancel');
    let ids = [];

    const selected = () => chks.filter((c) => c.checked).map((c) => c.value);
    function sync() {
        const n = selected().length;
        bulk.classList.toggle('d-none', n === 0);
        bulk.querySelector('span').textContent = n;
        document.getElementById('chkAll').checked = n > 0 && n === chks.length;
        chks.forEach((c) => c.closest('tr').classList.toggle('is-selected', c.checked));
    }
    chks.forEach((c) => c.addEventListener('change', sync));
    document.getElementById('chkAll')?.addEventListener('change', (e) => { chks.forEach((c) => { c.checked = e.target.checked; }); sync(); });

    function open(list) {
        ids = list;
        document.getElementById('cancelCount').textContent = ids.length;
        document.getElementById('cancelList').innerHTML = ids.map((id) => {
            const li = document.createElement('li');
            li.textContent = document.querySelector(`tr[data-id="${id}"]`).dataset.summary;
            return li.outerHTML;
        }).join('');
        form.reason.value = '';
        WMS.clearErrors(form);
        modal.show();
    }
    bulk.addEventListener('click', () => open(selected()));
    document.querySelectorAll('.js-cancel-one').forEach((b) => b.addEventListener('click', (e) => {
        e.stopPropagation();
        open([b.closest('tr').dataset.id]);
    }));
    document.querySelectorAll('[data-reason]').forEach((b) => b.addEventListener('click', () => { form.reason.value = b.dataset.reason; form.reason.focus(); }));
    modalEl.addEventListener('shown.bs.modal', () => form.reason.focus());

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('[type=submit]');
        btn.disabled = true;
        const r = await WMS.api(url, { method: 'POST', body: { ids, reason: form.reason.value.trim() } });
        btn.disabled = false;
        if (!r.ok) {
            const alert = form.querySelector('.form-alert');
            alert.textContent = r.data.message;
            alert.classList.remove('d-none');
            return;
        }
        location.reload(); // pesan sukses tampil sebagai toast (session flash)
    });
})();
</script>
@endpush
@endif
