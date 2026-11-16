@extends('layouts.app')
@section('title', 'Wave Planning')

@use('App\Support\Qty')
@section('content')
@php($canCreate = auth()->user()->can('access', ['OUT_WAVE', 'create']))

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">Wave Planning</span></div>
        <div class="btn-group btn-group-sm o-segment">
            <a href="{{ request()->fullUrlWithQuery(['tab' => null, 'page' => null, 'status' => null]) }}" class="btn btn-secondary {{ $tab === 'plan' ? 'active' : '' }}">Rencana @if ($open)<span class="badge rounded-pill bg-primary ms-1">{{ $open->count() }}</span>@endif</a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'waves', 'page' => null]) }}" class="btn btn-secondary {{ $tab === 'waves' ? 'active' : '' }}">Daftar Wave</a>
        </div>
    </div>
    @if ($tab === 'plan' && $canCreate)
        <button type="button" class="btn btn-sm btn-primary d-none" id="btnMakeWave" data-bs-toggle="modal" data-bs-target="#modalWave"><i class="bi bi-collection me-1"></i>Buat Wave (<span>0</span>)</button>
    @endif
    @include('partials.searchbar', ['placeholder' => $tab === 'plan' ? 'Cari shipment, customer, referensi...' : 'Cari no. wave / shipment...', 'filters' => array_filter([
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        'status' => $tab === 'waves' ? ['Status', collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()] : null,
    ])])
    @if ($waves)@include('partials.pager', ['paginator' => $waves])@endif
</div>

@if ($tab === 'plan')
<div class="o-list table-responsive">
    <table class="table table-hover" id="planTable">
        <thead><tr>
            @if ($canCreate)<th style="width:36px"><input type="checkbox" class="form-check-input" id="chkAll" aria-label="Pilih semua"></th>@endif
            <th>No. Shipment</th><th>Gudang</th><th>Owner</th><th>Customer / Tujuan</th><th>Referensi</th><th>Tgl Kirim</th><th>Prio</th><th>Aturan</th><th class="text-end">Item</th><th class="text-end">Qty (base)</th>
        </tr></thead>
        <tbody>
        @forelse ($open as $s)
            <tr data-id="{{ $s->id }}" data-wh="{{ $s->warehouse_id }}">
                @if ($canCreate)<td><input type="checkbox" class="form-check-input js-chk" value="{{ $s->id }}" aria-label="Pilih"></td>@endif
                <td class="o-td-title fw-semibold text-nowrap"><a href="{{ route('outbound.shipments.show', $s) }}" class="text-decoration-none">{{ $s->shipment_no }}</a></td>
                <td>{{ $s->warehouse->code }}</td>
                <td>{{ $s->owner->name }}</td>
                <td class="text-truncate" style="max-width:240px">{{ $s->ship_to_name ?? $s->customer->name }}</td>
                <td>{{ $s->reference_no }}</td>
                <td class="text-nowrap">{{ $s->expected_ship_date?->format('d/m/Y') }}
                    @if ($s->expected_ship_date && $s->expected_ship_date->lt(today()))<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis">lewat</span>@endif
                </td>
                <td>{{ $s->priority }}</td>
                <td>{{ $s->allocation_rule?->value ?? '-' }}</td>
                <td class="text-end">{{ $s->details_count }}</td>
                <td class="text-end">{{ Qty::fmt($s->qty_sum) }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="o-empty"><i class="bi bi-check2-circle"></i>Tidak ada shipment Open. Shipment yang sudah dikonfirmasi muncul di sini untuk digabung ke wave.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@else
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. Wave</th><th>Jenis</th><th>Gudang</th><th>Aturan</th><th class="text-end">Shipment</th><th class="text-end">Baris belum pick</th><th>Dibuat</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($waves as $w)
            <tr class="o-row-link" data-href="{{ route('outbound.waves.show', $w) }}">
                <td class="fw-semibold text-nowrap">{{ $w->wave_no }}</td>
                <td>{{ $w->typeLabel() }}</td>
                <td>{{ $w->warehouse->code }}</td>
                <td>{{ $w->allocation_rule?->value ?? 'Ikut shipment / item' }}</td>
                <td class="text-end">{{ $w->shipments_count }}</td>
                <td class="text-end">{{ $w->open_count }}</td>
                <td class="small text-nowrap">{{ $w->created_at->format('d/m H:i') }}<div class="text-muted">{{ $w->createdBy?->name }}</div></td>
                <td><span class="badge rounded-pill bg-{{ $w->status->color() }}-subtle text-{{ $w->status->color() }}-emphasis">{{ $w->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-collection"></i>Belum ada wave.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection

@if ($tab === 'plan' && $canCreate)
@push('modals')
<div class="modal fade" id="modalWave" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content o-input" method="POST" action="{{ route('outbound.waves.store') }}" data-ajax>
            @csrf
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-collection me-1"></i> Buat Wave</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="small text-muted mb-3" id="waveSummary"></div>
                <div class="js-ids"></div>
                <x-field label="Aturan alokasi" help="Menimpa aturan di shipment & item. Kosong = tiap shipment/item memakai aturannya sendiri.">
                    <select name="allocation_rule" class="form-select">
                        <option value="">Ikut shipment / item</option>
                        @foreach ($rules as $r)<option value="{{ $r->value }}">{{ $r->value }} - {{ $r->short() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Catatan"><input name="notes" class="form-control" maxlength="255"></x-field>
                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="run" value="1" id="waveRun" checked><label class="form-check-label" for="waveRun">Langsung <b>Run Wave</b> (alokasikan stok sekarang)</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Buat Wave</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endpush
@push('scripts')
<script>
(() => {
    const checks = [...document.querySelectorAll('.js-chk')];
    const all = document.getElementById('chkAll');
    const btn = document.getElementById('btnMakeWave');
    const modal = document.getElementById('modalWave');
    const selected = () => checks.filter((c) => c.checked);
    const sync = () => {
        const n = selected().length;
        btn.classList.toggle('d-none', n === 0);
        btn.querySelector('span').textContent = n;
        if (all) { all.checked = n > 0 && n === checks.length; all.indeterminate = n > 0 && n < checks.length; }
    };
    checks.forEach((c) => c.addEventListener('change', sync));
    all?.addEventListener('change', () => { checks.forEach((c) => { c.checked = all.checked; }); sync(); });
    modal.addEventListener('show.bs.modal', () => {
        const rows = selected().map((c) => c.closest('tr'));
        const ids = modal.querySelector('.js-ids');
        ids.innerHTML = rows.map((r) => `<input type="hidden" name="shipment_ids[]" value="${Number(r.dataset.id)}">`).join('');
        const whs = new Set(rows.map((r) => r.dataset.wh));
        modal.querySelector('#waveSummary').innerHTML = `<b>${rows.length} shipment</b> dipilih: ${rows.map((r) => r.querySelector('td.o-td-title').textContent.trim()).join(', ')}`
            + (whs.size > 1 ? '<div class="text-danger mt-1">Shipment harus dari gudang yang sama.</div>' : '');
    });
    sync();
})();
</script>
@endpush
@endif
