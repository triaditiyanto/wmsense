@extends('layouts.app')
@section('title', $item->sku)

@use('App\Support\Qty')
@php
    $me = auth()->user();
    $canEdit = $me->can('access', ['MST_ITEM', 'edit']);
    $canAdd = $me->can('access', ['MST_ITEM', 'create']);
    $canDelete = $me->can('access', ['MST_ITEM', 'delete']);
    $yes = fn ($v) => $v ? 'Ya' : 'Tidak';
@endphp

@section('content')
<div class="o-control-panel d-flex align-items-center gap-2">
    <div class="o-breadcrumb me-auto">
        <a href="{{ route('master.items.index') }}">Item</a><span class="sep">/</span><span class="current">{{ $item->sku }}</span>
    </div>
</div>

<div class="o-form-header">
    @if ($canEdit)
        <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalItem"
                data-action="{{ route('master.items.update', $item) }}" data-method="PUT" data-title="Ubah Item {{ $item->sku }}"
                data-record="{{ json_encode([
                    ...$item->only(['owner_id', 'item_class_id', 'base_uom_id', 'sku', 'name', 'description', 'barcode',
                        'is_lot_tracked', 'is_expiry_tracked', 'shelf_life_days', 'min_shelf_life_days', 'is_hazardous',
                        'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'is_active']),
                    'rotation_rule' => $item->rotation_rule->value,
                    'temperature_class' => $item->temperature_class->value,
                ]) }}">Ubah</button>
    @endif
    @if ($isUsed)
        <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Item sudah dipakai transaksi: owner &amp; base UOM terkunci.</span>
    @endif
</div>

<div class="o-sheet-bg">
    <div class="o-sheet">
        @unless ($item->is_active)<span class="o-ribbon bg-secondary-subtle text-secondary-emphasis">Arsip</span>@endunless
        <div class="o-doc-label">Item · {{ $item->sku }}</div>
        <h1 class="o-title">{{ $item->name }}</h1>

        <div class="o-group">
            <div>
                <div class="o-field"><span class="o-label">Owner</span><span class="o-value">{{ $item->owner->name }}</span></div>
                <div class="o-field"><span class="o-label">Group / Class</span><span class="o-value">{{ $item->itemClass->itemGroup->name }} › {{ $item->itemClass->name }}</span></div>
                <div class="o-field"><span class="o-label">Base UOM</span><span class="o-value">{{ $item->baseUom->code }} - {{ $item->baseUom->name }}</span></div>
                <div class="o-field"><span class="o-label">Barcode</span><span class="o-value font-monospace">{{ $item->barcode }}</span></div>
                <div class="o-field"><span class="o-label">Fisik / {{ $item->baseUom->code }}</span><span class="o-value">
                    @if ($item->weight_kg){{ Qty::fmt($item->weight_kg) }} kg @endif
                    @if ($item->length_cm) · {{ Qty::fmt($item->length_cm) }} × {{ Qty::fmt($item->width_cm) }} × {{ Qty::fmt($item->height_cm) }} cm @endif
                </span></div>
            </div>
            <div>
                <div class="o-field"><span class="o-label">Rotasi</span><span class="o-value">{{ $item->rotation_rule->value }} - {{ $item->rotation_rule->label() }}</span></div>
                <div class="o-field"><span class="o-label">Kelas suhu</span><span class="o-value">{{ $item->temperature_class->label() }}</span></div>
                <div class="o-field"><span class="o-label">Lot / Expired</span><span class="o-value">{{ $yes($item->is_lot_tracked) }} / {{ $yes($item->is_expiry_tracked) }}</span></div>
                <div class="o-field"><span class="o-label">Umur simpan</span><span class="o-value">
                    @if ($item->shelf_life_days){{ $item->shelf_life_days }} hari @endif
                    @if ($item->min_shelf_life_days)(min. sisa {{ $item->min_shelf_life_days }} hari saat terima)@endif
                </span></div>
                <div class="o-field"><span class="o-label">Barang B3</span><span class="o-value">{{ $yes($item->is_hazardous) }}</span></div>
            </div>
        </div>

        <div class="o-notebook">
            <ul class="nav nav-tabs">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUom" type="button">UOM &amp; Konversi</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabInfo" type="button">Deskripsi</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabUom">
                    <div class="o-list table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>UOM</th><th class="text-end">Isi ({{ $item->baseUom->code }})</th><th>Barcode</th><th class="text-end">Berat</th><th>Dimensi (cm)</th><th>Default</th>@if ($canDelete)<th style="width:40px"></th>@endif</tr></thead>
                            <tbody>
                            @foreach ($item->uoms as $u)
                                @php($isBase = $u->uom_id === $item->base_uom_id)
                                <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalUom"
                                        data-action="{{ route('master.items.uoms.update', [$item, $u]) }}" data-method="PUT" data-title="Ubah UOM {{ $u->uom->code }}"
                                        data-record="{{ json_encode([
                                            ...$u->only(['uom_id', 'barcode', 'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'is_default_inbound', 'is_default_outbound']),
                                            'conversion_factor' => rtrim(rtrim($u->conversion_factor, '0'), '.'),
                                            'is_base' => $isBase,
                                        ]) }}" @endif>
                                    <td class="fw-semibold">{{ $u->uom->code }} @if ($isBase)<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis ms-1">Dasar</span>@endif</td>
                                    <td class="text-end">{{ Qty::fmt($u->conversion_factor) }}</td>
                                    <td class="font-monospace">{{ $u->barcode }}</td>
                                    <td class="text-end">{{ $u->weight_kg ? Qty::fmt($u->weight_kg).' kg' : '' }}</td>
                                    <td class="text-muted">@if ($u->length_cm){{ Qty::fmt($u->length_cm) }} × {{ Qty::fmt($u->width_cm) }} × {{ Qty::fmt($u->height_cm) }}@endif</td>
                                    <td>
                                        @if ($u->is_default_inbound)<span class="badge rounded-pill bg-success-subtle text-success-emphasis">Inbound</span>@endif
                                        @if ($u->is_default_outbound)<span class="badge rounded-pill bg-info-subtle text-info-emphasis">Outbound</span>@endif
                                    </td>
                                    @if ($canDelete)
                                        <td class="text-end" onclick="event.stopPropagation()">
                                            @unless ($isBase)
                                                <form method="POST" action="{{ route('master.items.uoms.destroy', [$item, $u]) }}" data-ajax data-confirm="Hapus UOM {{ $u->uom->code }}?">
                                                    @csrf <input type="hidden" name="_method" value="DELETE">
                                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0 o-row-action"><i class="bi bi-trash"></i></button>
                                                </form>
                                            @endunless
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($canAdd)
                        <div class="o-add-line">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#modalUom" data-action="{{ route('master.items.uoms.store', $item) }}"
                               data-method="POST" data-title="Tambah UOM" data-record="{}">Tambah UOM</a>
                        </div>
                    @endif
                </div>
                <div class="tab-pane fade" id="tabInfo">
                    <div style="white-space:pre-line">{{ $item->description ?: '-' }}</div>
                    <div class="small text-muted mt-3">Dibuat {{ $item->created_at->format('d/m/Y H:i') }} · Diubah {{ $item->updated_at->format('d/m/Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
    @include('master.items._modal')

    <x-modal-form id="modalUom" title="UOM" size="modal-lg">
        <div class="o-group mb-0">
            <div>
                <x-field label="UOM" required>
                    <select name="uom_id" class="form-select">@foreach ($uoms as $u)<option value="{{ $u->id }}">{{ $u->code }} - {{ $u->name }}</option>@endforeach</select>
                </x-field>
                <x-field label="Isi ({{ $item->baseUom->code }})" required help="Jumlah {{ $item->baseUom->code }} dalam 1 UOM ini, mis. 1 CTN = 24.">
                    <input type="number" step="any" min="0" name="conversion_factor" class="form-control">
                </x-field>
                <x-field label="Barcode"><input name="barcode" class="form-control font-monospace"></x-field>
                <x-field label="Default inbound"><input type="hidden" name="is_default_inbound" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_default_inbound" value="1"></div></x-field>
                <x-field label="Default outbound"><input type="hidden" name="is_default_outbound" value="0"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_default_outbound" value="1"></div></x-field>
            </div>
            <div>
                <x-field label="Berat (kg)"><input type="number" step="any" min="0" name="weight_kg" class="form-control"></x-field>
                <x-field label="Panjang (cm)"><input type="number" step="any" min="0" name="length_cm" class="form-control"></x-field>
                <x-field label="Lebar (cm)"><input type="number" step="any" min="0" name="width_cm" class="form-control"></x-field>
                <x-field label="Tinggi (cm)"><input type="number" step="any" min="0" name="height_cm" class="form-control"></x-field>
            </div>
        </div>
    </x-modal-form>
@endpush

@push('scripts')
<script>
    // Baris UOM dasar: jenis UOM & isi (selalu 1) tidak bisa diubah
    document.getElementById('modalUom').addEventListener('wms:fill', (e) => {
        const f = e.detail.form;
        const isBase = !!e.detail.record.is_base;
        f.querySelector('[name=conversion_factor]').readOnly = isBase;
        f.querySelector('[name=uom_id]').style.pointerEvents = isBase ? 'none' : '';
        f.querySelector('[name=uom_id]').tabIndex = isBase ? -1 : 0;
    });
</script>
@endpush
