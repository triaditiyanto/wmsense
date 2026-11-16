@extends('layouts.app')
@section('title', 'LPN')

@section('content')
@php
    $me = auth()->user();
    $canEdit = $me->can('access', ['MST_LPN', 'edit']);
    $canVoid = $me->can('access', ['MST_LPN', 'delete']);
    $defaults = ['warehouse_id' => $warehouses->first()?->id, 'lpn_type' => 'PALLET', 'pallet_id' => $pallets->first()?->id];
@endphp
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        @can('access', ['MST_LPN', 'create'])
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGenerate"
                    data-action="{{ route('master.lpns.generate') }}" data-method="POST" data-title="Generate LPN"
                    data-record="{{ json_encode([...$defaults, 'count' => 10]) }}">Generate</button>
            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalLpn"
                    data-action="{{ route('master.lpns.store') }}" data-method="POST" data-title="Registrasi LPN"
                    data-record="{{ json_encode($defaults) }}">Registrasi Manual</button>
        @endcan
        <div class="o-breadcrumb"><span class="current">LPN</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari / scan nomor LPN...', 'filters' => [
        'status' => ['Status', collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
        'type' => ['Tipe', collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()],
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        'via' => ['Sumber', ['MASTER' => 'Master', 'RECEIVING' => 'Receiving']],
    ]])
    @include('partials.pager', ['paginator' => $lpns])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. LPN</th><th>Tipe</th><th>Jenis Pallet</th><th>Gudang</th><th>Lokasi</th><th>Status</th><th>Sumber</th><th>Didaftarkan</th><th></th></tr></thead>
        <tbody>
        @forelse ($lpns as $l)
            @php($editable = $canEdit && $l->status->isUsable())
            <tr @if ($editable) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalLpn"
                    data-action="{{ route('master.lpns.update', $l) }}" data-method="PUT" data-title="Ubah LPN {{ $l->lpn_no }}"
                    data-record="{{ json_encode(['lpn_no' => $l->lpn_no, 'warehouse_id' => $l->warehouse_id, 'lpn_type' => $l->lpn_type->value, 'pallet_id' => $l->pallet_id, 'notes' => $l->notes]) }}" @endif>
                <td class="fw-semibold font-monospace">{{ $l->lpn_no }}</td>
                <td>{{ $l->lpn_type->label() }}</td>
                <td>{{ $l->pallet?->name }}</td>
                <td>{{ $l->warehouse->code }}</td>
                <td class="font-monospace">{{ $l->location?->code }}</td>
                <td><span class="badge rounded-pill bg-{{ $l->status->color() }}-subtle text-{{ $l->status->color() }}-emphasis">{{ $l->status->label() }}</span></td>
                <td class="small">{{ $l->registered_via === 'RECEIVING' ? 'Receiving' : 'Master' }}</td>
                <td class="small text-muted">{{ $l->registeredBy?->name }} · {{ $l->created_at->format('d/m/Y') }}</td>
                <td class="text-end" onclick="event.stopPropagation()">
                    @if ($canVoid && $l->status->canTransitionTo(\App\Enums\LpnStatus::VOID))
                        <form method="POST" action="{{ route('master.lpns.void', $l) }}" data-ajax data-confirm="Void LPN {{ $l->lpn_no }}? LPN yang di-void tidak bisa dipakai lagi.">
                            @csrf <button type="submit" class="btn btn-sm btn-link text-danger p-0 o-row-action" title="Void"><i class="bi bi-x-octagon"></i></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="o-empty"><i class="bi bi-upc-scan"></i>Belum ada LPN. Gunakan <b>Generate</b> untuk membuat nomor LPN baru.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    {{-- Generate banyak LPN --}}
    <x-modal-form id="modalGenerate" title="Generate LPN" submit="Generate">
        <div class="alert alert-info small py-2">Nomor dibuat otomatis: <b>LPN-{GUDANG}-{TAHUN}-000001</b>, status <b>Kosong</b>, siap dicetak &amp; ditempel.</div>
        <x-field label="Gudang" required>
            <select name="warehouse_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>@endforeach</select>
        </x-field>
        <x-field label="Jumlah" required help="Maksimal {{ $maxGenerate }} per proses."><input type="number" name="count" min="1" max="{{ $maxGenerate }}" class="form-control"></x-field>
        @include('master.lpns._attributes')
    </x-modal-form>

    {{-- Registrasi manual / ubah --}}
    <x-modal-form id="modalLpn" title="LPN">
        <x-field label="No. LPN" required help="Isi barcode label (mis. SSCC dari supplier).">
            <input name="lpn_no" class="form-control text-uppercase font-monospace" data-lock-on-edit>
        </x-field>
        <x-field label="Gudang" required>
            <select name="warehouse_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>@endforeach</select>
        </x-field>
        @include('master.lpns._attributes')
    </x-modal-form>
@endpush

@push('scripts')
<script>
    // Jenis pallet hanya relevan untuk LPN bertipe PALLET
    document.querySelectorAll('#modalGenerate, #modalLpn').forEach((modal) => {
        const sync = () => {
            const isPallet = modal.querySelector('[name=lpn_type]').value === 'PALLET';
            modal.querySelector('.js-pallet-field').classList.toggle('d-none', !isPallet);
        };
        modal.addEventListener('change', sync);
        modal.addEventListener('wms:fill', (e) => {
            sync();
            // gudang tidak bisa diganti saat ubah
            modal.querySelector('[name=warehouse_id]').disabled = e.detail.form.querySelector('[name=_method]').value !== 'POST' && modal.id === 'modalLpn';
        });
    });
</script>
@endpush
