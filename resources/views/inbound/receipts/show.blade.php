@extends('layouts.app')
@section('title', $receipt->receipt_no)

@use('App\Support\Qty')
@use('App\Enums\ReceiptStatus')
@php
    $user = auth()->user();
    $editable = $receipt->status->isEditable();
    $canEdit = $editable && $user->can('access', ['INB_RECEIPT', 'edit']);
    $canAdd = $editable && $user->can('access', ['INB_RECEIPT', 'create']);
    $canDelete = $editable && $user->can('access', ['INB_RECEIPT', 'delete']);
    $canApprove = $user->can('access', ['INB_RECEIPT', 'approve']);
    $labelCounts = \App\Http\Controllers\Inbound\ReceiptLabelController::counts($receipt);
    $canPrepareLpn = in_array($receipt->status, [ReceiptStatus::DRAFT, ReceiptStatus::OPEN, ReceiptStatus::RECEIVING], true)
        && ($user->can('access', ['INB_RECEIVING', 'create']) || $user->can('access', ['MST_LPN', 'create']));

    // Langkah yang tampil di statusbar (Cancelled hanya muncul bila memang dibatalkan)
    $steps = [ReceiptStatus::DRAFT, ReceiptStatus::OPEN, ReceiptStatus::RECEIVING, ReceiptStatus::RECEIVED, ReceiptStatus::CLOSED];
    if ($receipt->status === ReceiptStatus::CANCELLED) {
        $steps = [ReceiptStatus::DRAFT, ReceiptStatus::CANCELLED];
    }
    $currentIndex = array_search($receipt->status, $steps, true);
@endphp

@section('content')
{{-- ============================== CONTROL PANEL ============================== --}}
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('inbound.receipts.index') }}">Receipt</a>
        <span class="sep">/</span>
        <span class="current">{{ $receipt->receipt_no }}</span>
    </div>
</div>

{{-- ============================== HEADER: tombol aksi + statusbar ============================== --}}
<div class="o-form-header">
    @if ($canApprove && $receipt->status->canTransitionTo(ReceiptStatus::OPEN))
        <form method="POST" action="{{ route('inbound.receipts.confirm', $receipt) }}" data-ajax
              data-confirm="Konfirmasi dokumen? Setelah dikonfirmasi, header & detail tidak bisa diubah lagi.">
            @csrf <button type="submit" class="btn btn-sm btn-primary">Konfirmasi</button>
        </form>
    @endif
    @if ($canEdit)
        <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalReceiptHeader"
                data-action="{{ route('inbound.receipts.update', $receipt) }}" data-method="PUT" data-title="Ubah Header {{ $receipt->receipt_no }}"
                data-record="{{ json_encode([
                    'warehouse_id' => $receipt->warehouse_id,
                    'owner_id' => $receipt->owner_id,
                    'receipt_type' => $receipt->receipt_type->value,
                    'expected_date' => $receipt->expected_date?->format('Y-m-d'),
                    'reference_no' => $receipt->reference_no,
                    'supplier_id' => $receipt->supplier_id,
                    'supplier_id_text' => $receipt->supplier ? "{$receipt->supplier->name} ({$receipt->supplier->code})" : null,
                    'customer_id' => $receipt->customer_id,
                    'customer_id_text' => $receipt->customer ? "{$receipt->customer->name} ({$receipt->customer->code})" : null,
                    'notes' => $receipt->notes,
                ]) }}">
            Ubah Header
        </button>
    @endif
    @if ($canApprove && $receipt->status->canTransitionTo(ReceiptStatus::CANCELLED))
        <form method="POST" action="{{ route('inbound.receipts.cancel', $receipt) }}" data-ajax data-confirm="Batalkan dokumen {{ $receipt->receipt_no }}?">
            @csrf <button type="submit" class="btn btn-sm btn-secondary">Batalkan</button>
        </form>
    @endif
    @if (in_array($receipt->status, [ReceiptStatus::OPEN, ReceiptStatus::RECEIVING, ReceiptStatus::RECEIVED, ReceiptStatus::CLOSED], true) && $user->can('access', ['INB_RECEIVING', 'view']))
        <a href="{{ route('inbound.receiving.show', $receipt) }}" class="btn btn-sm {{ $receipt->status === ReceiptStatus::OPEN ? 'btn-primary' : 'btn-secondary' }}">
            <i class="bi bi-box-arrow-in-down me-1"></i>Penerimaan
        </a>
    @endif
    @if ($receipt->status !== ReceiptStatus::CANCELLED)
        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalReceiptLabel">
            <i class="bi bi-qr-code me-1"></i>Cetak Label LPN
        </button>
    @endif

    <div class="o-statusbar">
        @foreach ($steps as $i => $step)
            <span class="o-step {{ $i < $currentIndex ? 'done' : '' }} {{ $i === $currentIndex ? 'current' : '' }} {{ $step === ReceiptStatus::CANCELLED ? 'cancel' : '' }}">{{ $step->label() }}</span>
        @endforeach
    </div>
</div>

{{-- ============================== SHEET ============================== --}}
<div class="o-sheet-bg">
    <div class="o-sheet">
        @if ($receipt->status === ReceiptStatus::CANCELLED)
            <span class="o-ribbon bg-danger-subtle text-danger-emphasis">Dibatalkan</span>
        @endif
        <div class="o-doc-label">Receipt</div>
        <h1 class="o-title">{{ $receipt->receipt_no }}</h1>

        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Gudang</span><span class="o-value">{{ $receipt->warehouse->code }} - {{ $receipt->warehouse->name }}</span></div>
                <div class="o-field"><span class="o-label">Owner</span><span class="o-value">{{ $receipt->owner->name }}</span></div>
                <div class="o-field"><span class="o-label">Tipe</span><span class="o-value">{{ $receipt->receipt_type->label() }}</span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">Tgl Kedatangan</span><span class="o-value">{{ $receipt->expected_date?->format('d/m/Y') }}</span></div>
                <div class="o-field"><span class="o-label">No. Referensi</span><span class="o-value">{{ $receipt->reference_no }}</span></div>
                <div class="o-field"><span class="o-label">{{ $receipt->receipt_type->usesCustomer() ? 'Customer' : 'Supplier' }}</span><span class="o-value">{{ $receipt->senderName() }}</span></div>
            </div>
        </div>

        {{-- ---------- Notebook (tab) ---------- --}}
        <div class="o-notebook">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetail" type="button">Detail Item</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabInfo" type="button">Info Lainnya</button></li>
            </ul>
            <div class="tab-content">
                {{-- ===== DETAIL ===== --}}
                <div class="tab-pane fade show active" id="tabDetail">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th style="width:40px">#</th><th>Item</th><th class="text-end">Qty</th><th>UOM</th>
                                <th class="text-end">Qty Base</th><th class="text-end">Diterima</th><th>Lot</th><th>Expired</th><th>Catatan</th>
                                @if ($canDelete)<th style="width:40px"></th>@endif
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($receipt->details as $d)
                                <tr @if ($canEdit) class="o-row-link" title="Klik untuk mengubah"
                                        data-bs-toggle="modal" data-bs-target="#modalReceiptDetail"
                                        data-action="{{ route('inbound.receipts.details.update', [$receipt, $d]) }}" data-method="PUT"
                                        data-title="Ubah Baris #{{ $d->line_no }}"
                                        data-record="{{ json_encode([
                                            'item_id' => $d->item_id,
                                            'item_text' => $d->item->sku.' - '.$d->item->name,
                                            'uom_id' => $d->uom_id,
                                            'qty_expected' => rtrim(rtrim($d->qty_expected, '0'), '.'),
                                            'lot_no' => $d->lot_no,
                                            'expiry_date' => $d->expiry_date?->format('Y-m-d'),
                                            'notes' => $d->notes,
                                        ]) }}" @endif>
                                    <td class="text-muted">{{ $d->line_no }}</td>
                                    <td><span class="fw-semibold">[{{ $d->item->sku }}]</span> {{ $d->item->name }}</td>
                                    <td class="text-end">{{ Qty::fmt($d->qty_expected) }}</td>
                                    <td>{{ $d->uom->code }}</td>
                                    <td class="text-end text-muted">{{ Qty::fmt($d->qty_expected_base) }}</td>
                                    <td class="text-end">{{ Qty::fmt($d->qty_received_base) }}</td>
                                    <td>{{ $d->lot_no }}</td>
                                    <td>{{ $d->expiry_date?->format('d/m/Y') }}</td>
                                    <td class="small text-muted">{{ $d->notes }}</td>
                                    @if ($canDelete)
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('inbound.receipts.details.destroy', [$receipt, $d]) }}" data-ajax
                                                  data-confirm="Hapus baris #{{ $d->line_no }} ({{ $d->item->sku }})?" onclick="event.stopPropagation()">
                                                @csrf <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit" class="btn btn-sm btn-link text-danger o-row-action p-0" title="Hapus"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                @unless ($canAdd)
                                    <tr><td colspan="10" class="o-empty"><i class="bi bi-list-ul"></i>Belum ada item.</td></tr>
                                @endunless
                            @endforelse
                            </tbody>
                            @if ($receipt->details->isNotEmpty())
                                <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end">Total</td>
                                    <td class="text-end">{{ Qty::fmt($receipt->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_expected_base), '0')) }}</td>
                                    <td class="text-end">{{ Qty::fmt($receipt->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_received_base), '0')) }}</td>
                                    <td colspan="{{ $canDelete ? 4 : 3 }}"></td>
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @if ($canAdd)
                        <div class="o-add-line border-bottom">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#modalReceiptDetail"
                               data-action="{{ route('inbound.receipts.details.store', $receipt) }}" data-method="POST" data-title="Tambah Item"
                               data-record="{}">Tambah baris</a>
                        </div>
                    @endif
                </div>

                {{-- ===== INFO LAIN ===== --}}
                <div class="tab-pane fade" id="tabInfo">
                    <div class="o-group">
                        <div>
                            <div class="o-field"><span class="o-label">Dibuat oleh</span><span class="o-value">{{ $receipt->createdBy?->name }}</span></div>
                            <div class="o-field"><span class="o-label">Dibuat pada</span><span class="o-value">{{ $receipt->created_at->format('d/m/Y H:i') }}</span></div>
                        </div>
                        <div>
                            @if ($receipt->confirmed_at)
                                <div class="o-field"><span class="o-label">Dikonfirmasi oleh</span><span class="o-value">{{ $receipt->confirmedBy?->name }}</span></div>
                                <div class="o-field"><span class="o-label">Dikonfirmasi pada</span><span class="o-value">{{ $receipt->confirmed_at->format('d/m/Y H:i') }}</span></div>
                            @endif
                            @if ($receipt->cancelled_at)
                                <div class="o-field"><span class="o-label">Dibatalkan oleh</span><span class="o-value">{{ $receipt->cancelledBy?->name }}</span></div>
                                <div class="o-field"><span class="o-label">Dibatalkan pada</span><span class="o-value">{{ $receipt->cancelled_at->format('d/m/Y H:i') }}</span></div>
                            @endif
                        </div>
                    </div>
                    <div class="o-field" style="grid-template-columns:150px 1fr"><span class="o-label">Catatan</span><span class="o-value" style="white-space:pre-line">{{ $receipt->notes }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Cetak label LPN untuk dokumen ini --}}
@if ($receipt->status !== ReceiptStatus::CANCELLED)
<form class="modal fade" id="modalReceiptLabel" tabindex="-1" method="POST" action="{{ route('inbound.receipts.labels', $receipt) }}" target="_blank">
    @csrf
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content o-input">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-qr-code me-1"></i> Cetak Label LPN</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-label-sources mb-3">
                    @if ($canPrepareLpn)
                        <label class="o-label-source"><input type="radio" name="source" value="new" class="form-check-input" @checked($labelCounts['used'] === 0)>
                            <span><b>Siapkan LPN baru</b><small>Buat nomor LPN kosong untuk ditempel di pallet sebelum barang diterima.</small></span></label>
                    @endif
                    <label class="o-label-source {{ $labelCounts['prepared'] ? '' : 'is-disabled' }}"><input type="radio" name="source" value="prepared" class="form-check-input" @disabled(! $labelCounts['prepared']) @checked(! $canPrepareLpn && $labelCounts['used'] === 0 && $labelCounts['prepared'])>
                        <span><b>Cetak ulang LPN yang disiapkan ({{ $labelCounts['prepared'] }})</b><small>LPN kosong yang sudah dibuat untuk dokumen ini.</small></span></label>
                    <label class="o-label-source {{ $labelCounts['used'] ? '' : 'is-disabled' }}"><input type="radio" name="source" value="used" class="form-check-input" @disabled(! $labelCounts['used']) @checked($labelCounts['used'] > 0)>
                        <span><b>LPN yang sudah diterima ({{ $labelCounts['used'] }})</b><small>Pallet tag lengkap dengan isi: item, qty, lot, expired.</small></span></label>
                </div>
                <div class="o-field" data-src="new"><label class="o-required">Jumlah LPN</label><div><input type="number" name="count" value="{{ max(1, $receipt->details->count()) }}" min="1" max="200" class="form-control"></div></div>
                <div class="o-field" data-src="new"><label>Jenis pallet</label><div>
                    <select name="pallet_id" class="form-select"><option value="">-</option>@foreach (\App\Models\Pallet::where('is_active', true)->orderBy('code')->get() as $p)<option value="{{ $p->id }}" @selected($loop->first)>{{ $p->name }}</option>@endforeach</select>
                </div></div>
                <div class="o-field"><label>Jenis kode</label><div>
                    <div class="btn-group btn-group-sm o-segment w-100">
                        @foreach (\App\Services\LabelService::SYMBOLOGIES as $k => $label)
                            <input type="radio" class="btn-check" name="symbology" id="rl-sym-{{ $k }}" value="{{ $k }}" @checked($k === 'qr')>
                            <label class="btn btn-secondary" for="rl-sym-{{ $k }}">{{ $k === 'qr' ? 'QR Code' : ($k === 'barcode' ? 'Barcode' : 'Keduanya') }}</label>
                        @endforeach
                    </div>
                </div></div>
                <div class="o-field"><label>Ukuran</label><div>
                    <select name="size" class="form-select">@foreach (\App\Services\LabelService::sizesFor('lpn') as $k => [$name])<option value="{{ $k }}" @selected($k === 'pallet')>{{ $name }}</option>@endforeach</select>
                </div></div>
                <div class="o-field"><label>Salinan</label><div><input type="number" name="copies" value="1" min="1" max="10" class="form-control" style="max-width:110px"></div></div>
                <input type="hidden" name="contents" value="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Cetak</button>
            </div>
        </div>
    </div>
    <div class="js-ids"></div>
</form>
@endif
    @include('inbound.receipts._header_modal')

    {{-- Modal DETAIL receipt --}}
    <div class="modal fade" id="modalReceiptDetail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content o-input" method="POST" data-ajax>
                @csrf <input type="hidden" name="_method" value="POST">
                <div class="modal-header"><h5 class="modal-title">Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-danger form-alert d-none"></div>
                    <div class="o-field" style="grid-template-columns:150px 1fr">
                        <label class="o-required">Item</label>
                        <div>
                            <select name="item_id" id="detailItem" placeholder="Ketik SKU / nama / barcode..."></select>
                            <div class="form-text">Item milik owner {{ $receipt->owner->name }}</div>
                        </div>
                    </div>
                    <div class="o-group mb-0">
                        <div>
                            <div class="o-field"><label class="o-required">Qty</label><input type="number" name="qty_expected" class="form-control" step="any" min="0"></div>
                            <div class="o-field">
                                <label class="o-required">UOM</label>
                                <select name="uom_id" id="detailUom" class="form-select"><option value="">pilih item dulu</option></select>
                            </div>
                            <div class="o-field"><label>Qty Base</label><input type="text" id="detailQtyBase" class="form-control" readonly tabindex="-1"></div>
                        </div>
                        <div>
                            <div class="o-field"><label>Lot <span id="lotHint" class="text-muted fw-normal small"></span></label><input name="lot_no" class="form-control"></div>
                            <div class="o-field"><label>Expired <span id="expHint" class="text-muted fw-normal small"></span></label><input type="date" name="expiry_date" class="form-control"></div>
                        </div>
                    </div>
                    <div class="o-field" style="grid-template-columns:150px 1fr"><label>Catatan</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
            </form>
        </div>
    </div>
@endpush

@push('scripts')
<script>
    // Cetak label LPN: "LPN baru" -> generate dulu (JSON) lalu buka halaman cetak dengan id hasil generate
    (() => {
        const f = document.getElementById('modalReceiptLabel');
        if (!f) return;
        const src = () => f.querySelector('[name=source]:checked')?.value;
        const sync = () => f.querySelectorAll('[data-src]').forEach((el) => { el.hidden = el.dataset.src !== src(); });
        f.addEventListener('change', sync); sync();
        f.addEventListener('submit', async (e) => {
            const ids = f.querySelector('.js-ids'); ids.innerHTML = '';
            const alert = f.querySelector('.form-alert'); alert.classList.add('d-none');
            if (!src()) { e.preventDefault(); return; }
            if (src() !== 'new') { setTimeout(() => bootstrap.Modal.getInstance(f)?.hide(), 100); return; }   // used / prepared: langsung cetak
            e.preventDefault();
            const win = window.open('about:blank', 'wmsReceiptLabel');   // buka tab saat masih dalam klik (anti popup-blocker)
            const res = await WMS.api(@json(route('inbound.receipts.labels.generate', $receipt)), { method: 'POST',
                body: { count: f.count.value, lpn_type: 'PALLET', pallet_id: f.pallet_id.value } });
            if (!res.ok) { win?.close(); alert.textContent = res.data?.message || 'Gagal membuat LPN.'; alert.classList.remove('d-none'); return; }
            res.data.ids.forEach((id) => ids.insertAdjacentHTML('beforeend', '<input type="hidden" name="ids[]" value="' + Number(id) + '">'));
            const radio = f.querySelector('[name=source][value=new]'); radio.value = 'ids';
            f.target = 'wmsReceiptLabel'; HTMLFormElement.prototype.submit.call(f); f.target = '_blank'; radio.value = 'new';
            WMS.showToast(res.data.message);
            bootstrap.Modal.getInstance(f).hide();
            setTimeout(() => location.reload(), 800);
        });
    })();
</script>
<script>
(() => {
    const modal = document.getElementById('modalReceiptDetail');
    if (!modal) return;
    const lookupUrl = @json(route('lookup.items'));
    const ownerId = @json($receipt->owner_id);
    const uomSel = document.getElementById('detailUom');
    const qtyInp = modal.querySelector('[name=qty_expected]');
    const baseInp = document.getElementById('detailQtyBase');
    let pendingUom = null; // UOM yang harus dipilih setelah opsi UOM terisi (mode ubah)

    const fetchItems = (q) => {
        const url = new URL(lookupUrl, location.origin);
        url.searchParams.set('owner_id', ownerId);
        url.searchParams.set('q', q);
        return fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
    };

    // Dropdown item dengan pencarian ke server (Tom Select)
    const itemTs = new TomSelect('#detailItem', {
        valueField: 'id', labelField: 'text', searchField: ['text'],
        preload: 'focus', maxOptions: 30, loadThrottle: 300,
        load(query, callback) { fetchItems(query).then(callback).catch(() => callback()); },
        onChange(value) { applyItem(this.options[value]); },
    });

    function applyItem(item) {
        uomSel.innerHTML = '';
        if (!item || !item.uoms) { uomSel.innerHTML = '<option value="">pilih item dulu</option>'; updateBase(); return; }
        item.uoms.forEach((u) => {
            const opt = new Option(u.factor === '1' ? u.code : `${u.code} (isi ${u.factor})`, u.id);
            opt.dataset.factor = u.factor;
            opt.dataset.code = u.code;
            uomSel.add(opt);
        });
        if (pendingUom) { uomSel.value = pendingUom; pendingUom = null; }
        document.getElementById('lotHint').textContent = item.is_lot_tracked ? '(wajib lot)' : '';
        document.getElementById('expHint').textContent = item.is_expiry_tracked ? '(ber-expired)' : '';
        updateBase();
    }

    function updateBase() {
        const factor = parseFloat(uomSel.selectedOptions[0]?.dataset.factor || 0);
        const qty = parseFloat(qtyInp.value || 0);
        const baseCode = uomSel.options[0]?.dataset.code || '';
        baseInp.value = factor && qty ? `${+(qty * factor).toFixed(4)} ${baseCode}` : '';
    }
    uomSel.addEventListener('change', updateBase);
    qtyInp.addEventListener('input', updateBase);

    // Saat modal dibuka (tambah / ubah): set item & UOM
    modal.addEventListener('wms:fill', async (e) => {
        const r = e.detail.record;
        itemTs.clear(true);
        uomSel.innerHTML = '<option value="">pilih item dulu</option>';
        baseInp.value = '';
        if (!r.item_id) return;

        pendingUom = String(r.uom_id);
        const items = await fetchItems(r.item_text.split(' - ')[0]);
        const item = items.find((i) => i.id === r.item_id) || { id: r.item_id, text: r.item_text, uoms: [] };
        itemTs.addOption(item);
        itemTs.setValue(String(item.id)); // memicu onChange -> applyItem
        qtyInp.value = r.qty_expected;
        updateBase();
    });
})();
</script>
@endpush
