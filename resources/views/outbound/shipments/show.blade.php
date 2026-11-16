@extends('layouts.app')
@section('title', $shipment->shipment_no)

@use('App\Support\Qty')
@use('App\Enums\ShipmentStatus')
@php
    $user = auth()->user();
    $editable = $shipment->status->isEditable();
    $canEdit = $editable && $user->can('access', ['OUT_SHIPMENT', 'edit']);
    $canAdd = $editable && $user->can('access', ['OUT_SHIPMENT', 'create']);
    $canDelete = $editable && $user->can('access', ['OUT_SHIPMENT', 'delete']);
    $canApprove = $user->can('access', ['OUT_SHIPMENT', 'approve']);
    $canGoWave = $shipment->status === ShipmentStatus::OPEN && $user->can('access', ['OUT_WAVE', 'create']);

    $steps = [ShipmentStatus::DRAFT, ShipmentStatus::OPEN, ShipmentStatus::ALLOCATED, ShipmentStatus::PICKING, ShipmentStatus::PICKED, ShipmentStatus::DISPATCHED];
    if ($shipment->status === ShipmentStatus::CANCELLED) $steps = [ShipmentStatus::DRAFT, ShipmentStatus::CANCELLED];
    if ($shipment->status === ShipmentStatus::WAVED) $steps = [ShipmentStatus::DRAFT, ShipmentStatus::OPEN, ShipmentStatus::WAVED, ShipmentStatus::ALLOCATED, ShipmentStatus::PICKING, ShipmentStatus::PICKED, ShipmentStatus::DISPATCHED];
    $currentIndex = array_search($shipment->status, $steps, true);
    $addresses = $shipment->customer->addresses->where('is_active', true)->map(fn ($a) => ['id' => $a->id, 'label' => $a->label, 'address' => $a->fullAddress(), 'is_default' => $a->is_default])->values();
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('outbound.shipments.index') }}">Shipment</a><span class="sep">/</span><span class="current">{{ $shipment->shipment_no }}</span>
    </div>
</div>

<div class="o-form-header">
    @if ($canApprove && $shipment->status->canTransitionTo(ShipmentStatus::OPEN))
        <form method="POST" action="{{ route('outbound.shipments.confirm', $shipment) }}" data-ajax data-confirm="Konfirmasi shipment? Setelah dikonfirmasi, header & detail tidak bisa diubah lagi.">
            @csrf <button type="submit" class="btn btn-sm btn-primary">Konfirmasi</button>
        </form>
    @endif
    @if ($canGoWave)
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalGoWave"><i class="bi bi-lightning-charge me-1"></i>Go Wave</button>
    @endif
    @if ($canEdit)
        <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalShipmentHeader"
                data-action="{{ route('outbound.shipments.update', $shipment) }}" data-method="PUT" data-title="Ubah Header {{ $shipment->shipment_no }}"
                data-record="{{ json_encode([
                    'warehouse_id' => $shipment->warehouse_id, 'owner_id' => $shipment->owner_id,
                    'customer_id' => $shipment->customer_id, 'customer_id_text' => "{$shipment->customer->name} ({$shipment->customer->code})",
                    'customer_address_id' => $shipment->customer_address_id, 'customer_addresses' => $addresses,
                    'order_date' => $shipment->order_date?->format('Y-m-d'), 'expected_ship_date' => $shipment->expected_ship_date?->format('Y-m-d'),
                    'reference_no' => $shipment->reference_no, 'allocation_rule' => $shipment->allocation_rule?->value, 'priority' => $shipment->priority, 'notes' => $shipment->notes,
                ]) }}">Ubah Header</button>
    @endif
    @if ($canApprove && $shipment->status->canTransitionTo(ShipmentStatus::CANCELLED))
        <form method="POST" action="{{ route('outbound.shipments.cancel', $shipment) }}" data-ajax data-confirm="Batalkan shipment {{ $shipment->shipment_no }}?">
            @csrf <button type="submit" class="btn btn-sm btn-secondary">Batalkan</button>
        </form>
    @endif
    @if ($shipment->wave && $user->can('access', ['OUT_WAVE', 'view']))
        <a href="{{ route('outbound.waves.show', $shipment->wave) }}" class="btn btn-sm btn-secondary"><i class="bi bi-collection me-1"></i>{{ $shipment->wave->wave_no }}</a>
    @endif
    @if ($shipment->deliveryOrder && $user->can('access', ['OUT_DISPATCH', 'view']))
        <a href="{{ route('outbound.dispatch.show', $shipment->deliveryOrder) }}" class="btn btn-sm btn-secondary"><i class="bi bi-truck me-1"></i>{{ $shipment->deliveryOrder->do_no }}</a>
    @endif
    <div class="o-statusbar">
        @foreach ($steps as $i => $step)
            <span class="o-step {{ $i < $currentIndex ? 'done' : '' }} {{ $i === $currentIndex ? 'current' : '' }} {{ $step === ShipmentStatus::CANCELLED ? 'cancel' : '' }}">{{ $step->label() }}</span>
        @endforeach
    </div>
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        @if ($shipment->status === ShipmentStatus::CANCELLED)<span class="o-ribbon bg-danger-subtle text-danger-emphasis">Dibatalkan</span>@endif
        <div class="o-doc-label">Shipment</div>
        <h1 class="o-title">{{ $shipment->shipment_no }}</h1>

        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Gudang</span><span class="o-value">{{ $shipment->warehouse->code }} - {{ $shipment->warehouse->name }}</span></div>
                <div class="o-field"><span class="o-label">Owner</span><span class="o-value">{{ $shipment->owner->name }}</span></div>
                <div class="o-field"><span class="o-label">Customer</span><span class="o-value">{{ $shipment->customer->name }}</span></div>
                <div class="o-field"><span class="o-label">Alamat kirim</span><span class="o-value">{{ $shipment->ship_to_name }}<div class="small text-muted">{{ $shipment->ship_to_address }}</div></span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">Tgl Order / Kirim</span><span class="o-value">{{ $shipment->order_date?->format('d/m/Y') ?? '-' }} / {{ $shipment->expected_ship_date?->format('d/m/Y') ?? '-' }}</span></div>
                <div class="o-field"><span class="o-label">No. Referensi</span><span class="o-value">{{ $shipment->reference_no }}</span></div>
                <div class="o-field"><span class="o-label">Aturan alokasi</span><span class="o-value">{{ $shipment->allocation_rule ? $shipment->allocation_rule->value.' - '.$shipment->allocation_rule->short() : 'Ikut item' }}</span></div>
                <div class="o-field"><span class="o-label">Prioritas</span><span class="o-value">{{ $shipment->priority }}</span></div>
            </div>
        </div>

        <div class="o-notebook">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetail" type="button">Detail Item</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAlloc" type="button">Alokasi & Picking <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $allocations->count() }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabInfo" type="button">Info Lainnya</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabDetail">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th style="width:40px">#</th><th>Item</th><th class="text-end">Qty</th><th>UOM</th><th class="text-end">Qty Base</th>
                                <th class="text-end">Teralokasi</th><th class="text-end">Dipick</th><th class="text-end">Dikirim</th><th>Lot</th><th>Catatan</th>
                                @if ($canDelete)<th style="width:40px"></th>@endif
                            </tr></thead>
                            <tbody>
                            @forelse ($shipment->details as $d)
                                @php($open = $d->qtyOpenBase())
                                <tr @if ($canEdit) class="o-row-link" title="Klik untuk mengubah" data-bs-toggle="modal" data-bs-target="#modalShipmentDetail"
                                        data-action="{{ route('outbound.shipments.details.update', [$shipment, $d]) }}" data-method="PUT" data-title="Ubah Baris #{{ $d->line_no }}"
                                        data-record="{{ json_encode(['item_id' => $d->item_id, 'item_text' => $d->item->sku.' - '.$d->item->name, 'uom_id' => $d->uom_id,
                                            'qty_ordered' => rtrim(rtrim($d->qty_ordered, '0'), '.'), 'lot_no' => $d->lot_no, 'notes' => $d->notes]) }}" @endif>
                                    <td class="text-muted">{{ $d->line_no }}</td>
                                    <td class="o-td-title"><span class="fw-semibold">[{{ $d->item->sku }}]</span> {{ $d->item->name }}</td>
                                    <td class="text-end">{{ Qty::fmt($d->qty_ordered) }}</td>
                                    <td>{{ $d->uom->code }}</td>
                                    <td class="text-end text-muted">{{ Qty::fmt($d->qty_ordered_base) }} {{ $d->item->baseUom->code }}</td>
                                    <td class="text-end">{{ Qty::fmt($d->qty_allocated_base) }}
                                        @if (! $editable && $shipment->status !== ShipmentStatus::OPEN && $shipment->status !== ShipmentStatus::CANCELLED && Qty::cmp($open, 0) > 0)
                                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis" title="Belum teralokasi">-{{ Qty::fmt($open) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ Qty::fmt($d->qty_picked_base) }}</td>
                                    <td class="text-end">{{ Qty::fmt($d->qty_shipped_base) }}</td>
                                    <td>{{ $d->lot_no }}</td>
                                    <td class="small text-muted">{{ $d->notes }}</td>
                                    @if ($canDelete)
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('outbound.shipments.details.destroy', [$shipment, $d]) }}" data-ajax data-confirm="Hapus baris #{{ $d->line_no }} ({{ $d->item->sku }})?" onclick="event.stopPropagation()">
                                                @csrf <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit" class="btn btn-sm btn-link text-danger o-row-action p-0" title="Hapus"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                @unless ($canAdd)<tr><td colspan="11" class="o-empty"><i class="bi bi-list-ul"></i>Belum ada item.</td></tr>@endunless
                            @endforelse
                            </tbody>
                            @if ($shipment->details->isNotEmpty())
                                <tfoot><tr>
                                    <td colspan="4" class="text-end">Total (base)</td>
                                    <td class="text-end">{{ Qty::fmt($totals['ordered']) }}</td><td class="text-end">{{ Qty::fmt($totals['allocated']) }}</td>
                                    <td class="text-end">{{ Qty::fmt($totals['picked']) }}</td><td class="text-end">{{ Qty::fmt($totals['shipped']) }}</td>
                                    <td colspan="{{ $canDelete ? 3 : 2 }}"></td>
                                </tr></tfoot>
                            @endif
                        </table>
                    </div>
                    @if ($canAdd)
                        <div class="o-add-line border-bottom">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#modalShipmentDetail" data-action="{{ route('outbound.shipments.details.store', $shipment) }}" data-method="POST" data-title="Tambah Item" data-record="{}">Tambah baris</a>
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade" id="tabAlloc">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Baris</th><th>Item</th><th>Lokasi</th><th>LPN</th><th>Lot / Expired</th><th class="text-end">Teralokasi</th><th class="text-end">Dipick</th><th>Aturan</th><th>Status</th><th>Staging</th><th>Oleh</th></tr></thead>
                            <tbody>
                            @forelse ($allocations as $a)
                                <tr>
                                    <td class="text-muted">#{{ $shipment->details->firstWhere('id', $a->shipment_detail_id)?->line_no }}</td>
                                    <td class="o-td-title fw-semibold">{{ $a->item->sku }}</td>
                                    <td class="fw-semibold">{{ $a->location->code }}</td>
                                    <td class="font-monospace small">{{ $a->lpn?->lpn_no ?? '-' }}</td>
                                    <td>{{ $a->lot_no ?? '-' }}@if ($a->expiry_date)<div class="small text-muted">Exp {{ $a->expiry_date->format('d/m/Y') }}</div>@endif</td>
                                    <td class="text-end">{{ Qty::fmt($a->qty_allocated_base) }}</td>
                                    <td class="text-end">{{ Qty::fmt($a->qty_picked_base) }}</td>
                                    <td>{{ $a->rule?->value }}</td>
                                    <td><span class="badge rounded-pill bg-{{ $a->status->color() }}-subtle text-{{ $a->status->color() }}-emphasis">{{ $a->status->label() }}</span>@if ($a->short_reason)<div class="small text-muted">{{ $a->short_reason }}</div>@endif</td>
                                    <td>{{ $a->stagingLocation?->code ?? '-' }}</td>
                                    <td class="small">{{ $a->picker?->name }}@if ($a->picked_at)<div class="text-muted">{{ $a->picked_at->format('d/m H:i') }}</div>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="o-empty"><i class="bi bi-collection"></i>Belum dialokasikan. @if ($shipment->status === ShipmentStatus::OPEN) Tekan <b>Go Wave</b> atau gabungkan lewat <b>Wave Planning</b>. @endif</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tabInfo">
                    <div class="o-group">
                        <div>
                            <div class="o-field"><span class="o-label">Dibuat oleh</span><span class="o-value">{{ $shipment->createdBy?->name }}</span></div>
                            <div class="o-field"><span class="o-label">Dibuat pada</span><span class="o-value">{{ $shipment->created_at->format('d/m/Y H:i') }}</span></div>
                        </div>
                        <div>
                            @if ($shipment->confirmed_at)
                                <div class="o-field"><span class="o-label">Dikonfirmasi</span><span class="o-value">{{ $shipment->confirmedBy?->name }} · {{ $shipment->confirmed_at->format('d/m/Y H:i') }}</span></div>
                            @endif
                            @if ($shipment->cancelled_at)
                                <div class="o-field"><span class="o-label">Dibatalkan</span><span class="o-value">{{ $shipment->cancelledBy?->name }} · {{ $shipment->cancelled_at->format('d/m/Y H:i') }}</span></div>
                            @endif
                        </div>
                    </div>
                    <div class="o-field" style="grid-template-columns:150px 1fr"><span class="o-label">Catatan</span><span class="o-value" style="white-space:pre-line">{{ $shipment->notes }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
    @include('outbound.shipments._header_modal')

    @if ($canGoWave)
    {{-- Go Wave: wave berisi shipment ini saja, langsung dialokasikan --}}
    <div class="modal fade" id="modalGoWave" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content o-input" method="POST" action="{{ route('outbound.shipments.go-wave', $shipment) }}" data-ajax>
                @csrf
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-lightning-charge me-1"></i> Go Wave {{ $shipment->shipment_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-danger form-alert d-none"></div>
                    <p class="small text-muted">Shipment ini dialokasikan <b>sendirian</b> dalam satu wave dan langsung siap dipick. Untuk menggabungkan beberapa shipment, pakai <b>Wave Planning</b>.</p>
                    <x-field label="Aturan alokasi">
                        <select name="allocation_rule" class="form-select">
                            <option value="">{{ $shipment->allocation_rule ? 'Ikut shipment ('.$shipment->allocation_rule->value.')' : 'Ikut item' }}</option>
                            @foreach ($rules as $r)<option value="{{ $r->value }}">{{ $r->value }} - {{ $r->short() }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-lightning-charge me-1"></i>Go Wave</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
            </form>
        </div>
    </div>
    @endif

    {{-- Modal DETAIL shipment --}}
    <div class="modal fade" id="modalShipmentDetail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content o-input" method="POST" data-ajax>
                @csrf <input type="hidden" name="_method" value="POST">
                <div class="modal-header"><h5 class="modal-title">Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-danger form-alert d-none"></div>
                    <div class="o-field" style="grid-template-columns:150px 1fr">
                        <label class="o-required">Item</label>
                        <div><select name="item_id" id="detailItem" placeholder="Ketik SKU / nama / barcode..."></select><div class="form-text">Item milik owner {{ $shipment->owner->name }}</div></div>
                    </div>
                    <div class="o-group mb-0">
                        <div>
                            <div class="o-field"><label class="o-required">Qty</label><input type="number" name="qty_ordered" class="form-control" step="any" min="0"></div>
                            <div class="o-field"><label class="o-required">UOM</label><select name="uom_id" id="detailUom" class="form-select"><option value="">pilih item dulu</option></select></div>
                            <div class="o-field"><label>Qty Base</label><input type="text" id="detailQtyBase" class="form-control" readonly tabindex="-1"></div>
                        </div>
                        <div>
                            <x-field label="Batasi lot" help="Kosong = lot mana pun sesuai aturan rotasi."><input name="lot_no" class="form-control"></x-field>
                            <div class="o-field"><label>Catatan</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
            </form>
        </div>
    </div>
@endpush

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('modalShipmentDetail');
    if (!modal) return;
    const lookupUrl = @json(route('lookup.items'));
    const ownerId = @json($shipment->owner_id);
    const uomSel = document.getElementById('detailUom');
    const qtyInp = modal.querySelector('[name=qty_ordered]');
    const baseInp = document.getElementById('detailQtyBase');
    let pendingUom = null;
    const fetchItems = (q) => {
        const url = new URL(lookupUrl, location.origin);
        url.searchParams.set('owner_id', ownerId); url.searchParams.set('q', q);
        return fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
    };
    const itemTs = new TomSelect('#detailItem', {
        valueField: 'id', labelField: 'text', searchField: ['text'], preload: 'focus', maxOptions: 30, loadThrottle: 300,
        load(query, callback) { fetchItems(query).then(callback).catch(() => callback()); },
        onChange(value) { applyItem(this.options[value]); },
    });
    function applyItem(item) {
        uomSel.innerHTML = '';
        if (!item || !item.uoms) { uomSel.innerHTML = '<option value="">pilih item dulu</option>'; updateBase(); return; }
        item.uoms.forEach((u) => { const opt = new Option(u.factor === '1' ? u.code : `${u.code} (isi ${u.factor})`, u.id); opt.dataset.factor = u.factor; opt.dataset.code = u.code; uomSel.add(opt); });
        if (pendingUom) { uomSel.value = pendingUom; pendingUom = null; }
        updateBase();
    }
    function updateBase() {
        const factor = parseFloat(uomSel.selectedOptions[0]?.dataset.factor || 0);
        const qty = parseFloat(qtyInp.value || 0);
        baseInp.value = factor && qty ? `${+(qty * factor).toFixed(4)} ${uomSel.options[0]?.dataset.code || ''}` : '';
    }
    uomSel.addEventListener('change', updateBase);
    qtyInp.addEventListener('input', updateBase);
    modal.addEventListener('wms:fill', async (e) => {
        const r = e.detail.record;
        itemTs.clear(true); uomSel.innerHTML = '<option value="">pilih item dulu</option>'; baseInp.value = '';
        if (!r.item_id) return;
        pendingUom = String(r.uom_id);
        const items = await fetchItems(r.item_text.split(' - ')[0]);
        const item = items.find((i) => i.id === r.item_id) || { id: r.item_id, text: r.item_text, uoms: [] };
        itemTs.addOption(item); itemTs.setValue(String(item.id));
        qtyInp.value = r.qty_ordered; updateBase();
    });
})();
</script>
@endpush
