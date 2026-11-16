@extends('layouts.app')
@section('title', 'Dispatch')

@use('App\Support\Qty')
@section('content')
@php($canCreate = auth()->user()->can('access', ['OUT_DISPATCH', 'create']))

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        <div class="o-breadcrumb"><span class="current">Dispatch</span></div>
        <div class="btn-group btn-group-sm o-segment">
            <a href="{{ request()->fullUrlWithQuery(['tab' => null, 'page' => null, 'status' => null]) }}" class="btn btn-secondary {{ $tab === 'ready' ? 'active' : '' }}">Siap kirim @if ($ready)<span class="badge rounded-pill bg-primary ms-1">{{ $ready->count() }}</span>@endif</a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'do', 'page' => null]) }}" class="btn btn-secondary {{ $tab === 'do' ? 'active' : '' }}">Surat Jalan</a>
        </div>
    </div>
    @if ($tab === 'ready' && $canCreate)
        <button type="button" class="btn btn-sm btn-primary d-none" id="btnMakeDo" data-bs-toggle="modal" data-bs-target="#modalDo"><i class="bi bi-truck me-1"></i>Buat Surat Jalan (<span>0</span>)</button>
    @elseif ($canCreate)
        <button type="button" class="btn btn-sm btn-primary" id="btnNewDo" data-bs-toggle="modal" data-bs-target="#modalDo"><i class="bi bi-plus-lg me-1"></i>Baru</button>
    @endif
    @include('partials.searchbar', ['placeholder' => $tab === 'ready' ? 'Cari shipment, customer, referensi...' : 'Cari no. DO, kendaraan, sopir, shipment...', 'filters' => array_filter([
        'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
        'status' => $tab === 'do' ? ['Status', collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()] : null,
    ])])
    @if ($dos)@include('partials.pager', ['paginator' => $dos])@endif
</div>

@if ($tab === 'ready')
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr>
            @if ($canCreate)<th style="width:36px"><input type="checkbox" class="form-check-input" id="chkAll" aria-label="Pilih semua"></th>@endif
            <th>No. Shipment</th><th>Gudang</th><th>Owner</th><th>Customer / Tujuan</th><th>Referensi</th><th>Tgl Kirim</th><th>Wave</th><th class="text-end">Item</th><th class="text-end">Qty dipick</th>
        </tr></thead>
        <tbody>
        @forelse ($ready as $s)
            <tr data-id="{{ $s->id }}" data-wh="{{ $s->warehouse_id }}">
                @if ($canCreate)<td><input type="checkbox" class="form-check-input js-chk" value="{{ $s->id }}" aria-label="Pilih"></td>@endif
                <td class="o-td-title fw-semibold text-nowrap"><a href="{{ route('outbound.shipments.show', $s) }}" class="text-decoration-none">{{ $s->shipment_no }}</a></td>
                <td>{{ $s->warehouse->code }}</td>
                <td>{{ $s->owner->name }}</td>
                <td class="text-truncate" style="max-width:260px">{{ $s->ship_to_name ?? $s->customer->name }}<div class="small text-muted text-truncate">{{ $s->ship_to_address }}</div></td>
                <td>{{ $s->reference_no }}</td>
                <td class="text-nowrap">{{ $s->expected_ship_date?->format('d/m/Y') }}</td>
                <td class="small">{{ $s->wave?->wave_no }}</td>
                <td class="text-end">{{ $s->details_count }}</td>
                <td class="text-end">{{ Qty::fmt($s->picked_sum) }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="o-empty"><i class="bi bi-check2-circle"></i>Tidak ada shipment siap kirim. Shipment yang selesai dipick muncul di sini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@else
<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>No. DO</th><th>Gudang</th><th>Tgl Kirim</th><th>Kendaraan / Sopir</th><th>Ekspedisi</th><th class="text-end">Shipment</th><th>Dibuat</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($dos as $d)
            <tr class="o-row-link" data-href="{{ route('outbound.dispatch.show', $d) }}">
                <td class="fw-semibold text-nowrap">{{ $d->do_no }}</td>
                <td>{{ $d->warehouse->code }}</td>
                <td class="text-nowrap">{{ $d->ship_date?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $d->vehicle_no ?? '-' }}<div class="small text-muted">{{ $d->driver_name }}</div></td>
                <td>{{ $d->carrier ?? '-' }}</td>
                <td class="text-end">{{ $d->shipments_count }}</td>
                <td class="small text-nowrap">{{ $d->created_at->format('d/m H:i') }}<div class="text-muted">{{ $d->createdBy?->name }}</div></td>
                <td><span class="badge rounded-pill bg-{{ $d->status->color() }}-subtle text-{{ $d->status->color() }}-emphasis">{{ $d->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="o-empty"><i class="bi bi-truck"></i>Belum ada Surat Jalan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection

@if ($canCreate)
@push('modals')
<div class="modal fade" id="modalDo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content o-input" method="POST" action="{{ route('outbound.dispatch.store') }}" data-ajax>
            @csrf
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-truck me-1"></i> Buat Surat Jalan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                @if ($tab === 'ready')
                    <div class="small text-muted mb-3" id="doSummary"></div>
                    <div class="js-ids"></div>
                @else
                    <p class="small text-muted">Surat Jalan dibuat <b>kosong</b>; shipment siap kirim ditambahkan di halaman Surat Jalan lewat <b>Tambah shipment</b>.</p>
                    <x-field label="Gudang" required>
                        <select name="warehouse_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>@endforeach</select>
                    </x-field>
                @endif
                @include('outbound.dispatch._header_fields')
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Buat Surat Jalan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endpush
@endif
@if ($tab === 'do' && $canCreate)
@push('scripts')
<script>
    document.getElementById('modalDo').addEventListener('show.bs.modal', (e) => { const d = e.target.querySelector('[name=ship_date]'); if (!d.value) d.value = new Date().toISOString().slice(0, 10); });
</script>
@endpush
@endif
@if ($tab === 'ready' && $canCreate)
@push('scripts')
<script>
(() => {
    const checks = [...document.querySelectorAll('.js-chk')];
    const all = document.getElementById('chkAll');
    const btn = document.getElementById('btnMakeDo');
    const modal = document.getElementById('modalDo');
    const selected = () => checks.filter((c) => c.checked);
    const sync = () => {
        const n = selected().length;
        btn.classList.toggle('d-none', n === 0); btn.querySelector('span').textContent = n;
        if (all) { all.checked = n > 0 && n === checks.length; all.indeterminate = n > 0 && n < checks.length; }
    };
    checks.forEach((c) => c.addEventListener('change', sync));
    all?.addEventListener('change', () => { checks.forEach((c) => { c.checked = all.checked; }); sync(); });
    modal.addEventListener('show.bs.modal', () => {
        const rows = selected().map((c) => c.closest('tr'));
        modal.querySelector('.js-ids').innerHTML = rows.map((r) => `<input type="hidden" name="shipment_ids[]" value="${Number(r.dataset.id)}">`).join('');
        const whs = new Set(rows.map((r) => r.dataset.wh));
        modal.querySelector('#doSummary').innerHTML = `<b>${rows.length} shipment</b>: ${rows.map((r) => r.querySelector('td.o-td-title').textContent.trim()).join(', ')}`
            + (whs.size > 1 ? '<div class="text-danger mt-1">Shipment harus dari gudang yang sama.</div>' : '');
        if (!modal.querySelector('[name=ship_date]').value) modal.querySelector('[name=ship_date]').value = new Date().toISOString().slice(0, 10);
    });
    sync();
})();
</script>
@endpush
@endif
