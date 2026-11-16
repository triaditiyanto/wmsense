@extends('layouts.app')
@section('title', $wave->wave_no)

@use('App\Support\Qty')
@use('App\Enums\WaveStatus')
@php
    $user = auth()->user();
    $canRun = $user->can('access', ['OUT_WAVE', 'create']);
    $canRealloc = $user->can('access', ['OUT_WAVE', 'edit']) && in_array($wave->status, [WaveStatus::ALLOCATED, WaveStatus::PICKING], true);
    $canDelete = $user->can('access', ['OUT_WAVE', 'delete']);
    $steps = [WaveStatus::PLANNED, WaveStatus::ALLOCATED, WaveStatus::PICKING, WaveStatus::PICKED];
    if ($wave->status === WaveStatus::CANCELLED) $steps = [WaveStatus::PLANNED, WaveStatus::CANCELLED];
    $currentIndex = array_search($wave->status, $steps, true);
    $openLines = $lines->where('status', \App\Enums\AllocationStatus::OPEN)->count();
    $items = $wave->shipments->flatMap(fn ($s) => $s->details->map(fn ($d) => ['id' => $d->item_id, 'owner_id' => $s->owner_id, 'text' => $d->item->sku.' - '.$d->item->name, 'lot' => $d->lot_no]))->unique('id')->values();
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('outbound.waves.index', ['tab' => 'waves']) }}">Wave Planning</a><span class="sep">/</span><span class="current">{{ $wave->wave_no }}</span>
    </div>
</div>

<div class="o-form-header">
    @if ($canRun && $wave->status === WaveStatus::PLANNED)
        <form method="POST" action="{{ route('outbound.waves.allocate', $wave) }}" data-ajax data-confirm="Run Wave: alokasikan stok untuk semua shipment di wave ini?">
            @csrf <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-play-fill me-1"></i>Run Wave</button>
        </form>
    @endif
    @if ($canRun && in_array($wave->status, [WaveStatus::ALLOCATED, WaveStatus::PICKING], true) && $perShipment->contains(fn ($p) => $p['short'] !== ''))
        <form method="POST" action="{{ route('outbound.waves.allocate', $wave) }}" data-ajax data-confirm="Alokasikan sisa yang belum terpenuhi (stok baru)?">
            @csrf <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-play-fill me-1"></i>Alokasi sisa</button>
        </form>
    @endif
    @if ($canRealloc)
        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalRealloc"><i class="bi bi-arrow-repeat me-1"></i>Alokasi ulang</button>
    @endif
    @if ($wave->status->canPick() && $user->can('access', ['OUT_PICKING', 'view']))
        <a href="{{ route('outbound.picking.index', ['q' => $wave->wave_no]) }}" class="btn btn-sm {{ $wave->status === WaveStatus::ALLOCATED ? 'btn-primary' : 'btn-secondary' }}"><i class="bi bi-cart-check me-1"></i>Picking</a>
    @endif
    @if ($canRun && $addable->isNotEmpty())
        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalAddShipment"><i class="bi bi-plus-lg me-1"></i>Tambah shipment</button>
    @endif
    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalPreview"><i class="bi bi-sort-down me-1"></i>Simulasi urutan</button>
    @if ($canDelete && $wave->status->canCancel())
        <form method="POST" action="{{ route('outbound.waves.cancel', $wave) }}" data-ajax data-confirm="Batalkan wave {{ $wave->wave_no }}? Alokasi dilepas, semua shipment kembali Open.">
            @csrf <button type="submit" class="btn btn-sm btn-secondary">Batalkan wave</button>
        </form>
    @endif
    <div class="o-statusbar">
        @foreach ($steps as $i => $step)
            <span class="o-step {{ $i < $currentIndex ? 'done' : '' }} {{ $i === $currentIndex ? 'current' : '' }} {{ $step === WaveStatus::CANCELLED ? 'cancel' : '' }}">{{ $step->label() }}</span>
        @endforeach
    </div>
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        @if ($wave->status === WaveStatus::CANCELLED)<span class="o-ribbon bg-danger-subtle text-danger-emphasis">Dibatalkan</span>@endif
        <div class="o-doc-label">{{ $wave->typeLabel() }}</div>
        <h1 class="o-title">{{ $wave->wave_no }}</h1>
        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Gudang</span><span class="o-value">{{ $wave->warehouse->code }} - {{ $wave->warehouse->name }}</span></div>
                <div class="o-field"><span class="o-label">Aturan alokasi</span><span class="o-value">{{ $wave->allocation_rule ? $wave->allocation_rule->value.' - '.$wave->allocation_rule->short() : 'Ikut shipment / item' }}</span></div>
                <div class="o-field"><span class="o-label">Staging</span><span class="o-value">{{ $wave->stagingLocation?->code ?? '-' }}</span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">Dibuat</span><span class="o-value">{{ $wave->createdBy?->name }} · {{ $wave->created_at->format('d/m/Y H:i') }}</span></div>
                <div class="o-field"><span class="o-label">Dialokasikan</span><span class="o-value">{{ $wave->allocated_at ? ($wave->allocatedBy?->name.' · '.$wave->allocated_at->format('d/m/Y H:i')) : '-' }}</span></div>
                <div class="o-field"><span class="o-label">Picking</span><span class="o-value">{{ $wave->picking_started_at?->format('d/m/Y H:i') ?? '-' }} → {{ $wave->picked_at?->format('d/m/Y H:i') ?? '-' }}</span></div>
            </div>
        </div>
        @if ($wave->notes)<div class="o-field" style="grid-template-columns:150px 1fr"><span class="o-label">Catatan</span><span class="o-value">{{ $wave->notes }}</span></div>@endif

        <div class="o-notebook">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabShip" type="button">Shipment <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $wave->shipments->count() }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLines" type="button">Hasil alokasi / pick list <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $lines->count() }}</span></button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabShip">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>No. Shipment</th><th>Customer / Tujuan</th><th>Prio</th><th>Aturan</th><th class="text-end">Qty pesan</th><th class="text-end">Teralokasi</th><th class="text-end">Dipick</th><th>Kurang stok</th><th>Status</th>@if ($canDelete)<th></th>@endif</tr></thead>
                            <tbody>
                            @forelse ($perShipment as $p)
                                @php($s = $p['shipment'])
                                <tr>
                                    <td class="o-td-title fw-semibold text-nowrap"><a href="{{ route('outbound.shipments.show', $s) }}" class="text-decoration-none">{{ $s->shipment_no }}</a></td>
                                    <td>{{ $s->ship_to_name ?? $s->customer->name }}</td>
                                    <td>{{ $s->priority }}</td>
                                    <td>{{ $s->allocation_rule?->value ?? 'item' }}</td>
                                    <td class="text-end">{{ Qty::fmt($p['ordered']) }}</td>
                                    <td class="text-end">{{ Qty::fmt($p['allocated']) }}</td>
                                    <td class="text-end">{{ Qty::fmt($p['picked']) }}</td>
                                    <td class="small">@if ($p['short'] !== '' && $wave->status !== WaveStatus::PLANNED)<span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ $p['short'] }}</span>@else -@endif</td>
                                    <td><span class="badge rounded-pill bg-{{ $s->status->color() }}-subtle text-{{ $s->status->color() }}-emphasis">{{ $s->status->label() }}</span></td>
                                    @if ($canDelete)
                                        <td class="text-end">
                                            @if (in_array($s->status, [\App\Enums\ShipmentStatus::WAVED, \App\Enums\ShipmentStatus::ALLOCATED], true) && $wave->status->canCancel())
                                                <form method="POST" action="{{ route('outbound.waves.shipments.remove', [$wave, $s]) }}" data-ajax data-confirm="Lepas {{ $s->shipment_no }} dari wave? Alokasinya dilepas, shipment kembali Open.">
                                                    @csrf <input type="hidden" name="_method" value="DELETE">
                                                    <button type="submit" class="btn btn-sm btn-link text-danger o-row-action p-0" title="Lepas dari wave"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="10" class="o-empty"><i class="bi bi-inbox"></i>Wave kosong.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tabLines">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th>Lokasi</th><th>Item</th><th>Shipment</th><th>LPN</th><th>Lot / Expired</th><th class="text-end">Qty</th><th class="text-end">Dipick</th><th>Aturan</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($lines as $i => $a)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td class="o-td-title fw-semibold">{{ $a->location->code }}</td>
                                    <td><b>{{ $a->item->sku }}</b> <span class="small text-muted">{{ $a->item->name }}</span></td>
                                    <td class="text-nowrap">{{ $a->shipment->shipment_no }}</td>
                                    <td class="font-monospace small">{{ $a->lpn?->lpn_no ?? '-' }}</td>
                                    <td>{{ $a->lot_no ?? '-' }}@if ($a->expiry_date)<div class="small text-muted">Exp {{ $a->expiry_date->format('d/m/Y') }}</div>@endif</td>
                                    <td class="text-end">{{ Qty::fmt($a->qty_allocated_base) }} {{ $a->item->baseUom->code }}</td>
                                    <td class="text-end">{{ Qty::fmt($a->qty_picked_base) }}</td>
                                    <td>{{ $a->rule?->value }}</td>
                                    <td><span class="badge rounded-pill bg-{{ $a->status->color() }}-subtle text-{{ $a->status->color() }}-emphasis">{{ $a->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="o-empty"><i class="bi bi-collection"></i>Belum dialokasikan. Tekan <b>Run Wave</b>.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
@if ($canRealloc)
<div class="modal fade" id="modalRealloc" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content o-input" method="POST" action="{{ route('outbound.waves.reallocate', $wave) }}" data-ajax>
            @csrf
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-arrow-repeat me-1"></i> Alokasi ulang {{ $wave->wave_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <p class="small text-muted">Semua baris yang <b>belum dipick</b> ({{ $openLines }}) dilepas lalu dialokasikan lagi dari awal. Baris yang sudah dipick tidak berubah.</p>
                <x-field label="Aturan alokasi">
                    <select name="allocation_rule" class="form-select">
                        <option value="">Ikut shipment / item</option>
                        @foreach ($rules as $r)<option value="{{ $r->value }}" @selected($wave->allocation_rule === $r)>{{ $r->value }} - {{ $r->short() }}</option>@endforeach
                    </select>
                </x-field>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Alokasi ulang</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endif
@if ($canRun && $addable->isNotEmpty())
<div class="modal fade" id="modalAddShipment" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content o-input" method="POST" action="{{ route('outbound.waves.shipments.add', $wave) }}" data-ajax>
            @csrf
            <div class="modal-header"><h5 class="modal-title">Tambah shipment ke {{ $wave->wave_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-list"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th style="width:36px"></th><th>No. Shipment</th><th>Customer</th><th>Tgl Kirim</th><th>Prio</th></tr></thead>
                    <tbody>
                    @foreach ($addable as $s)
                        <tr><td><input type="checkbox" class="form-check-input" name="shipment_ids[]" value="{{ $s->id }}" id="add-{{ $s->id }}"></td>
                            <td><label for="add-{{ $s->id }}" class="fw-semibold">{{ $s->shipment_no }}</label></td><td>{{ $s->ship_to_name ?? $s->customer->name }}</td>
                            <td>{{ $s->expected_ship_date?->format('d/m/Y') }}</td><td>{{ $s->priority }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Tambahkan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endif
{{-- Simulasi urutan kandidat stok per aturan (tidak mengubah data) --}}
<div class="modal fade" id="modalPreview" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content o-input">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-sort-down me-1"></i> Simulasi urutan pengambilan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-7"><select class="form-select form-select-sm" id="pvItem">@foreach ($items as $it)<option value="{{ $it['id'] }}" data-owner="{{ $it['owner_id'] }}" data-lot="{{ $it['lot'] }}">{{ $it['text'] }}</option>@endforeach</select></div>
                    <div class="col-md-5"><div class="btn-group btn-group-sm o-segment w-100" id="pvRule">
                        @foreach ($rules as $r)<button type="button" class="btn btn-secondary {{ ($wave->allocation_rule ?? $rules[0]) === $r ? 'active' : '' }}" data-rule="{{ $r->value }}" title="{{ $r->short() }}">{{ $r->value }}</button>@endforeach
                    </div></div>
                </div>
                <div class="small text-muted mb-2" id="pvHint"></div>
                <div class="o-list"><table class="table table-sm mb-0"><thead><tr><th>#</th><th>Lokasi</th><th>LPN</th><th>Lot</th><th>Expired</th><th>Diterima</th><th class="text-end">Tersedia</th></tr></thead><tbody id="pvBody"></tbody></table></div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
(() => {
    const m = document.getElementById('modalPreview');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const hints = { FIFO: 'Masuk terlama dulu (received_at).', FEFO: 'Expired terdekat dulu; tanpa expired paling akhir.', LIFO: 'Masuk terbaru dulu.', LEFO: 'Expired terjauh dulu.' };
    async function run() {
        const item = m.querySelector('#pvItem').selectedOptions[0];
        const rule = m.querySelector('#pvRule .active')?.dataset.rule || 'FIFO';
        if (!item) return;
        m.querySelector('#pvHint').textContent = `${rule}: ${hints[rule]}`;
        const r = await WMS.api(@json(route('outbound.waves.preview')), { method: 'POST', body: { warehouse_id: @json($wave->warehouse_id), item_id: item.value, owner_id: item.dataset.owner, rule, lot_no: item.dataset.lot || null } });
        m.querySelector('#pvBody').innerHTML = r.ok && r.data.length ? r.data.map((x, i) => `<tr><td class="text-muted">${i + 1}</td><td class="fw-semibold">${esc(x.location)}</td><td class="font-monospace small">${esc(x.lpn || '-')}</td><td>${esc(x.lot_no || '-')}</td><td>${esc(x.expiry_date || '-')}</td><td>${esc(x.received_at || '-')}</td><td class="text-end">${esc(x.qty_available)}</td></tr>`).join('')
            : `<tr><td colspan="7" class="text-center text-muted py-3">${r.ok ? 'Tidak ada stok tersedia.' : esc(r.data.message)}</td></tr>`;
    }
    m.addEventListener('show.bs.modal', run);
    m.querySelector('#pvItem').addEventListener('change', run);
    m.querySelector('#pvRule').addEventListener('click', (e) => { const b = e.target.closest('[data-rule]'); if (!b) return; m.querySelectorAll('#pvRule .btn').forEach((x) => x.classList.toggle('active', x === b)); run(); });
})();
</script>
@endpush
