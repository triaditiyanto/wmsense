@extends('layouts.app')
@section('title', 'Cetak Label')

@section('content')
@php
    $isLpn = $type === 'lpn';
    $canGenerate = $isLpn && auth()->user()->can('access', ['MST_LPN', 'create']);
    $filters = $isLpn
        ? [
            'status' => ['Status', collect(\App\Enums\LpnStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
            'lpn_type' => ['Tipe', collect(\App\Enums\LpnType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()],
            'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
            'printed' => ['Cetak', ['never' => 'Belum pernah dicetak', 'printed' => 'Sudah dicetak']],
        ]
        : [
            'warehouse' => ['Gudang', $warehouses->pluck('code', 'id')->all()],
            'zone' => ['Zona', $zones->mapWithKeys(fn ($z) => [$z->id => $z->warehouse->code.' · '.$z->name])->all()],
            'function' => ['Fungsi', \App\Http\Controllers\Master\LabelController::functionOptions()],
            'level' => ['Level', collect(range(1, 8))->mapWithKeys(fn ($n) => [$n => 'Level '.$n])->all()],
            'printed' => ['Cetak', ['never' => 'Belum pernah dicetak', 'printed' => 'Sudah dicetak']],
        ];
    $filterQuery = collect(request()->query())->except(['page'])->all();
@endphp

<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        @if ($canGenerate)
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGenLabel"><i class="bi bi-plus-lg me-1"></i>LPN baru &amp; cetak</button>
        @endif
        <div class="o-breadcrumb"><span class="current">Cetak Label</span></div>
        <div class="btn-group btn-group-sm o-segment">
            <a href="{{ route('master.labels.index', 'lpn') }}" class="btn btn-secondary {{ $isLpn ? 'active' : '' }}"><i class="bi bi-stack me-1"></i>LPN</a>
            <a href="{{ route('master.labels.index', 'location') }}" class="btn btn-secondary {{ $isLpn ? '' : 'active' }}"><i class="bi bi-geo-alt me-1"></i>Lokasi</a>
        </div>
    </div>
    @include('partials.searchbar', ['placeholder' => $isLpn ? 'Cari / scan nomor LPN...' : 'Cari kode lokasi...', 'filters' => $filters])
    @include('partials.pager', ['paginator' => $rows])
</div>

<div class="o-label-layout">
    <div class="o-list table-responsive">
        <table class="table table-hover table-sm align-middle" id="labelTable">
            <thead><tr>
                <th style="width:36px"><input type="checkbox" class="form-check-input" id="chkAll" aria-label="Pilih semua"></th>
                @if ($isLpn)
                    <th>No. LPN</th><th>Tipe</th><th>Gudang</th><th>Lokasi</th><th>Status</th><th>Dicetak</th>
                @else
                    <th>Kode lokasi</th><th>Fungsi</th><th>Zona</th><th>Aisle · Bay · Level</th><th>Dicetak</th>
                @endif
            </tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr class="o-row-link js-row">
                    <td><input type="checkbox" class="form-check-input js-chk" value="{{ $r->id }}" aria-label="Pilih"></td>
                    @if ($isLpn)
                        <td class="fw-semibold font-monospace o-td-title">{{ $r->lpn_no }}</td>
                        <td>{{ $r->lpn_type->label() }}</td>
                        <td>{{ $r->warehouse->code }}</td>
                        <td class="font-monospace">{{ $r->location?->code }}</td>
                        <td><span class="badge rounded-pill bg-{{ $r->status->color() }}-subtle text-{{ $r->status->color() }}-emphasis">{{ $r->status->label() }}</span></td>
                    @else
                        <td class="fw-semibold font-monospace o-td-title">{{ $r->code }}</td>
                        <td>{{ $r->locationFunction()->label() }}</td>
                        <td>{{ $r->zone->name }}</td>
                        <td class="small">{{ collect([$r->aisle, $r->bay, $r->level])->filter()->join(' · ') ?: '-' }}</td>
                    @endif
                    <td class="small text-nowrap">
                        @if ($r->print_count)
                            <span class="text-success"><i class="bi bi-printer-fill me-1"></i>{{ $r->print_count }}x</span>
                            <span class="text-muted">· {{ \Carbon\Carbon::parse($r->last_printed_at)->format('d/m/y H:i') }}</span>
                        @else
                            <span class="text-warning-emphasis"><i class="bi bi-printer me-1"></i>Belum</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="o-empty"><i class="bi bi-upc"></i>Tidak ada {{ $isLpn ? 'LPN' : 'lokasi' }} sesuai filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- ============ Pengaturan cetak ============ --}}
    <form class="o-panel o-label-panel" id="printForm" method="POST" action="{{ route('master.labels.print') }}" target="_blank">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="o-panel-title"><i class="bi bi-printer me-1"></i> Pengaturan cetak</div>
        <div class="p-3 d-flex flex-column gap-3">
            <div>
                <label class="form-label small fw-semibold mb-1">Jenis kode</label>
                <div class="btn-group btn-group-sm o-segment w-100" role="group">
                    @foreach ($symbologies as $k => $label)
                        <input type="radio" class="btn-check" name="symbology" id="sym-{{ $k }}" value="{{ $k }}" @checked($k === 'qr')>
                        <label class="btn btn-secondary" for="sym-{{ $k }}">{{ $k === 'qr' ? 'QR Code' : ($k === 'barcode' ? 'Barcode' : 'Keduanya') }}</label>
                    @endforeach
                </div>
                <div class="form-text" id="symHint">QR paling mudah di-scan kamera HP (dari sudut mana pun, label kecil pun bisa).</div>
            </div>
            <div class="row g-2">
                <div class="col-8">
                    <label class="form-label small fw-semibold mb-1">Ukuran label</label>
                    <select name="size" class="form-select form-select-sm">
                        @foreach ($sizes as $k => [$name])<option value="{{ $k }}" @selected($k === ($isLpn ? 'std' : 'std'))>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label small fw-semibold mb-1">Salinan</label>
                    <input type="number" name="copies" value="1" min="1" max="10" class="form-control form-control-sm">
                </div>
            </div>
            @if ($isLpn)
                <div class="form-check form-switch">
                    <input type="hidden" name="contents" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="contents" value="1" id="optContents">
                    <label class="form-check-label small" for="optContents">Tampilkan isi LPN (item, qty, lot, expired) <span class="text-muted">— pallet tag</span></label>
                </div>
            @else
                <div class="form-check form-switch">
                    <input type="hidden" name="level_color" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="level_color" value="1" id="optLevel" checked>
                    <label class="form-check-label small" for="optLevel">Warna per level rak <span class="text-muted">(mudah dibaca dari jauh)</span></label>
                </div>
            @endif

            <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold mb-0">Pratinjau</label>
                    <span class="small text-muted" id="previewCode"></span>
                </div>
                <div class="o-label-preview" id="previewBox"><iframe id="previewFrame" title="Pratinjau label"></iframe></div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" id="btnPrintSel" disabled><i class="bi bi-printer me-1"></i>Cetak terpilih (<span id="selCount">0</span>)</button>
                <button type="submit" class="btn btn-secondary" id="btnPrintAll" @disabled($total === 0 || $total > $maxLabels)
                        title="{{ $total > $maxLabels ? 'Lebih dari '.$maxLabels.' label: persempit filter' : '' }}">
                    <i class="bi bi-collection me-1"></i>Cetak semua sesuai filter ({{ number_format($total) }})
                </button>
            </div>
            <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Label dibuka di tab baru lalu dialog cetak muncul. Pakai printer label (Zebra/TSC) atau kertas stiker A4; bisa juga <b>Simpan sebagai PDF</b>.</div>
        </div>
        <div id="printIds"></div>
    </form>
</div>
@endsection

@push('modals')
@if ($canGenerate)
    <form class="modal fade" id="modalGenLabel" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content o-input">
                <div class="modal-header"><h5 class="modal-title">LPN baru &amp; cetak</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small py-2">Nomor LPN dibuat otomatis (status <b>Kosong</b>), lalu labelnya langsung dicetak dengan pengaturan di panel kanan.</div>
                    <div class="alert alert-danger form-alert d-none"></div>
                    <x-field label="Gudang" required>
                        <select name="warehouse_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Jumlah" required help="Maksimal {{ min($maxGenerate, $maxLabels) }}."><input type="number" name="count" value="10" min="1" max="{{ min($maxGenerate, $maxLabels) }}" class="form-control"></x-field>
                    <x-field label="Tipe" required>
                        <select name="lpn_type" class="form-select">@foreach (\App\Enums\LpnType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Jenis pallet">
                        <select name="pallet_id" class="form-select"><option value="">-</option>@foreach ($pallets as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                    </x-field>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Buat &amp; cetak</button>
                </div>
            </div>
        </div>
    </form>
@endif
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('printForm');
    const chkAll = document.getElementById('chkAll');
    const chks = () => [...document.querySelectorAll('.js-chk')];
    const firstId = chks()[0]?.value;
    const filterQuery = @json($filterQuery);
    const printUrl = @json(route('master.labels.print'));
    let mode = 'selected';

    const selected = () => chks().filter((c) => c.checked).map((c) => c.value);
    function refresh() {
        const n = selected().length;
        document.getElementById('selCount').textContent = n;
        document.getElementById('btnPrintSel').disabled = n === 0;
        chkAll.checked = n > 0 && n === chks().length;
        chkAll.indeterminate = n > 0 && n < chks().length;
        preview();
    }

    // ---- pratinjau: label pertama yang dipilih (atau baris pertama) dengan pengaturan saat ini
    let timer;
    function preview() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const id = selected()[0] || firstId;
            const frame = document.getElementById('previewFrame');
            if (!id) { frame.removeAttribute('src'); return; }
            const p = new URLSearchParams(new FormData(form));
            p.delete('_token'); p.delete('ids[]'); p.set('ids[]', id); p.set('preview', '1');
            frame.src = printUrl + '?' + p.toString();
            const row = chks().find((c) => c.value === id)?.closest('tr');
            document.getElementById('previewCode').textContent = row?.querySelector('.o-td-title')?.textContent || '';
        }, 150);
    }
    // skala pratinjau supaya label (mm) muat di panel
    document.getElementById('previewFrame').addEventListener('load', (e) => {
        const f = e.target; const doc = f.contentDocument; const lbl = doc?.querySelector('.lbl');
        if (!lbl) return;
        const box = document.getElementById('previewBox');
        const w = lbl.offsetWidth, h = lbl.offsetHeight;
        const scale = Math.min(1, (box.clientWidth - 16) / w);
        f.style.width = w + 'px'; f.style.height = h + 'px';
        f.style.transform = `scale(${scale})`;
        box.style.height = (h * scale + 16) + 'px';
    });

    const hints = {
        qr: 'QR paling mudah di-scan kamera HP (dari sudut mana pun, label kecil pun bisa).',
        barcode: 'Barcode Code 128: cocok untuk scanner genggam. Untuk kamera HP pakai label lebar (100 mm) dan cahaya cukup.',
        both: 'QR untuk kamera HP + barcode untuk scanner genggam (label 50 x 25 mm hanya memuat QR).',
    };
    form.addEventListener('change', (e) => {
        if (e.target.name === 'symbology') document.getElementById('symHint').textContent = hints[e.target.value];
        if (e.target.name === 'size' && document.getElementById('optContents')) document.getElementById('optContents').checked = e.target.value === 'pallet';
        if (e.target.classList.contains('js-chk')) return;
        preview();
    });

    chkAll.addEventListener('change', () => { chks().forEach((c) => { c.checked = chkAll.checked; }); refresh(); });
    document.getElementById('labelTable').addEventListener('change', (e) => { if (e.target.classList.contains('js-chk')) refresh(); });
    document.querySelectorAll('.js-row').forEach((tr) => tr.addEventListener('click', (e) => {
        if (e.target.closest('input')) return;
        const c = tr.querySelector('.js-chk'); c.checked = !c.checked; refresh();
    }));

    document.getElementById('btnPrintSel').addEventListener('click', () => { mode = 'selected'; });
    document.getElementById('btnPrintAll').addEventListener('click', () => { mode = 'all'; });
    form.addEventListener('submit', () => {
        const box = document.getElementById('printIds');
        box.innerHTML = '';
        const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; box.append(i); };
        if (mode === 'all') {
            add('all', '1');
            Object.entries(filterQuery).forEach(([k, v]) => { if (!['type', 'symbology', 'size', 'copies'].includes(k)) add(k, v); });
        } else {
            selected().forEach((id) => add('ids[]', id));
        }
    });

    // ---- LPN baru & cetak
    const gen = document.getElementById('modalGenLabel');
    gen?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const alert = gen.querySelector('.form-alert'); alert.classList.add('d-none');
        const btn = gen.querySelector('[type=submit]'); btn.disabled = true;
        // buka tab cetak SEKARANG (masih dalam klik user) supaya tidak diblokir popup blocker
        const win = window.open('about:blank', 'wmsLabelPrint');
        const res = await WMS.api(@json(route('master.labels.generate')), { method: 'POST', body: Object.fromEntries(new FormData(gen)) });
        btn.disabled = false;
        if (!res.ok) {
            win?.close();
            alert.textContent = res.data?.message || 'Gagal membuat LPN.';
            alert.classList.remove('d-none');
            return;
        }
        const box = document.getElementById('printIds'); box.innerHTML = '';
        res.data.ids.forEach((id) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; box.append(i); });
        form.target = 'wmsLabelPrint';
        HTMLFormElement.prototype.submit.call(form);   // tanpa memicu handler submit (ids sudah diisi)
        form.target = '_blank';
        bootstrap.Modal.getInstance(gen).hide();
        setTimeout(() => location.reload(), 400);      // daftar memuat LPN baru + toast dari flash
    });

    refresh();
})();
</script>
@endpush
