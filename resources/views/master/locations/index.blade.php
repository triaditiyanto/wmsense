@extends('layouts.app')
@section('title', 'Lokasi')

@section('content')
@php($canEdit = auth()->user()->can('access', ['MST_LOCATION', 'edit']))
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <x-new-button access="MST_LOCATION" modal="modalLocation" :action="route('master.locations.store')" title="Lokasi Baru"
                      :record="['status' => 'ACTIVE']" />
        @can('access', ['MST_LOCATION', 'create'])
            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGenerate"
                    data-action="{{ route('master.locations.generate') }}" data-method="POST" data-title="Generate Lokasi"
                    data-record="{{ json_encode(['bay_from' => 1, 'bay_to' => 10, 'level_from' => 1, 'level_to' => 4]) }}">
                <i class="bi bi-magic me-1"></i>Generate
            </button>
        @endcan
        <div class="o-breadcrumb"><span class="current">Lokasi</span></div>
    </div>
    @include('partials.searchbar', ['placeholder' => 'Cari kode lokasi...', 'filters' => [
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        'zone' => ['Zona', $zones->mapWithKeys(fn ($z) => [$z->id => $z->warehouse->code.' / '.$z->code])->all()],
        'class' => ['Kelas', $classes->pluck('name', 'id')->all()],
        'status' => ['Status', collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
    ]])
    @include('partials.pager', ['paginator' => $locations])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Kode</th><th>Gudang</th><th>Zona</th><th>Kelas</th><th>Tipe</th><th>Aisle</th><th>Bay</th><th>Level</th><th class="text-end">Urutan Pick</th><th>Kapasitas</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($locations as $l)
            <tr @if ($canEdit) class="o-row-link" data-bs-toggle="modal" data-bs-target="#modalLocation"
                    data-action="{{ route('master.locations.update', $l) }}" data-method="PUT" data-title="Ubah Lokasi {{ $l->code }}"
                    data-record="{{ json_encode([
                        'zone_id' => $l->zone_id, 'location_class_id' => $l->location_class_id, 'code' => $l->code, 'status' => $l->status->value,
                        'aisle' => $l->aisle, 'bay' => $l->bay, 'level' => $l->level, 'position' => $l->position,
                        'pick_sequence' => $l->pick_sequence, 'temperature_class' => $l->temperature_class?->value,
                        'max_lpn' => $l->max_lpn, 'max_weight_kg' => $l->max_weight_kg, 'max_volume_m3' => $l->max_volume_m3,
                    ]) }}" @endif>
                <td class="fw-semibold font-monospace">{{ $l->code }}</td>
                <td>{{ $l->warehouse->code }}</td>
                <td>{{ $l->zone->code }}</td>
                <td>{{ $l->locationClass->name }}</td>
                <td><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">{{ $l->locationClass->locationType->location_function->label() }}</span></td>
                <td>{{ $l->aisle }}</td>
                <td>{{ $l->bay }}</td>
                <td>{{ $l->level }}</td>
                <td class="text-end text-muted">{{ $l->pick_sequence }}</td>
                <td>@include('master.locations._capacity', ['c' => $capacity[$l->id]])</td>
                <td><span class="badge rounded-pill bg-{{ $l->status->color() }}-subtle text-{{ $l->status->color() }}-emphasis">{{ $l->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="11" class="o-empty"><i class="bi bi-grid-3x3"></i>Tidak ada lokasi. Gunakan <b>Generate</b> untuk membuat banyak lokasi sekaligus.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
    <x-modal-form id="modalLocation" title="Lokasi" size="modal-lg">
        <div class="o-group mb-0">
            <div>
                <x-field label="Kode" required help="Isi barcode label lokasi."><input name="code" class="form-control text-uppercase font-monospace"></x-field>
                <x-field label="Zona" required>
                    <select name="zone_id" class="form-select">
                        <option value=""></option>
                        @foreach ($zoneOptions as $wh => $zs)
                            <optgroup label="{{ $wh }}">@foreach ($zs as $z)<option value="{{ $z->id }}">{{ $z->code }} - {{ $z->name }}</option>@endforeach</optgroup>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Kelas" required>
                    <select name="location_class_id" class="form-select">
                        <option value=""></option>
                        @foreach ($classOptions as $type => $cs)
                            <optgroup label="{{ $type }}">@foreach ($cs as $c)<option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }}</option>@endforeach</optgroup>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Status" required>
                    <select name="status" class="form-select">
                        @foreach ($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Kelas suhu" help="Kosong = ikut zona.">
                    <select name="temperature_class" class="form-select">
                        <option value="">(ikut zona)</option>
                        @foreach ($temperatures as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                    </select>
                </x-field>
            </div>
            <div>
                <x-field label="Aisle"><input name="aisle" class="form-control"></x-field>
                <x-field label="Bay"><input name="bay" class="form-control"></x-field>
                <x-field label="Level" help="1 = paling bawah."><input type="number" min="1" name="level" class="form-control"></x-field>
                <x-field label="Posisi"><input name="position" class="form-control"></x-field>
                <x-field label="Urutan pick"><input type="number" min="0" name="pick_sequence" class="form-control"></x-field>
                <div class="o-separator mt-2">Kapasitas (kosong = ikut kelas)</div>
                <x-field label="Maks LPN"><input type="number" min="0" name="max_lpn" class="form-control"></x-field>
                <x-field label="Berat maks (kg)"><input type="number" step="any" min="0" name="max_weight_kg" class="form-control"></x-field>
                <x-field label="Volume maks (m³)"><input type="number" step="any" min="0" name="max_volume_m3" class="form-control"></x-field>
            </div>
        </div>
    </x-modal-form>

    <x-modal-form id="modalGenerate" title="Generate Lokasi" submit="Generate">
        <div class="alert alert-info small py-2">Kode dibuat dengan pola <b>ZONA-AISLE-BAY-LEVEL</b>. Kode yang sudah ada akan dilewati.</div>
        <x-field label="Zona" required>
            <select name="zone_id" class="form-select js-gen">
                <option value=""></option>
                @foreach ($zoneOptions as $wh => $zs)
                    <optgroup label="{{ $wh }}">@foreach ($zs as $z)<option value="{{ $z->id }}" data-code="{{ $z->code }}">{{ $z->code }} - {{ $z->name }}</option>@endforeach</optgroup>
                @endforeach
            </select>
        </x-field>
        <x-field label="Kelas" required>
            <select name="location_class_id" class="form-select">
                <option value=""></option>
                @foreach ($classOptions as $type => $cs)
                    <optgroup label="{{ $type }}">@foreach ($cs as $c)<option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }}</option>@endforeach</optgroup>
                @endforeach
            </select>
        </x-field>
        <x-field label="Aisle" required help="Contoh: 01-04 atau A,B,C"><input name="aisles" class="form-control js-gen" placeholder="01-04"></x-field>
        <x-field label="Bay" required>
            <div class="d-flex align-items-center gap-2"><input type="number" min="1" name="bay_from" class="form-control js-gen"><span>s/d</span><input type="number" min="1" name="bay_to" class="form-control js-gen"></div>
        </x-field>
        <x-field label="Level" required>
            <div class="d-flex align-items-center gap-2"><input type="number" min="1" name="level_from" class="form-control js-gen"><span>s/d</span><input type="number" min="1" name="level_to" class="form-control js-gen"></div>
        </x-field>
        <x-field label="Pratinjau"><div id="genPreview" class="text-muted small pt-1">-</div></x-field>
    </x-modal-form>
@endpush

@push('scripts')
<script>
(() => {
    // Pratinjau jumlah & contoh kode lokasi yang akan dibuat
    const modal = document.getElementById('modalGenerate');
    const val = (n) => modal.querySelector(`[name="${n}"]`).value;
    function aisles(input) {
        const out = [];
        input.toUpperCase().split(',').map((s) => s.trim()).filter(Boolean).forEach((p) => {
            const m = p.match(/^(\d+)\s*-\s*(\d+)$/);
            if (m) { const w = Math.max(m[1].length, 2); for (let i = +m[1]; i <= +m[2]; i++) out.push(String(i).padStart(w, '0')); }
            else out.push(/^\d+$/.test(p) ? p.padStart(2, '0') : p);
        });
        return out;
    }
    function preview() {
        const zone = modal.querySelector('[name=zone_id]').selectedOptions[0]?.dataset.code;
        const a = aisles(val('aisles'));
        const bays = Math.max(0, val('bay_to') - val('bay_from') + 1);
        const levels = Math.max(0, val('level_to') - val('level_from') + 1);
        const total = a.length * bays * levels;
        const el = document.getElementById('genPreview');
        if (!zone || !total) { el.textContent = '-'; return; }
        const code = (ai, b, l) => `${zone}-${ai}-${String(b).padStart(2, '0')}-${l}`;
        el.innerHTML = `<b>${total.toLocaleString('id-ID')}</b> lokasi: ${code(a[0], val('bay_from'), val('level_from'))} … ${code(a[a.length - 1], val('bay_to'), val('level_to'))}`;
    }
    modal.addEventListener('input', preview);
    modal.addEventListener('change', preview);
    modal.addEventListener('wms:fill', preview);
})();
</script>
@endpush
