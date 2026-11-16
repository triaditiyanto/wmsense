@extends('layouts.mobile')
@section('title', 'Cek Stok')
@section('subtitle', 'Lokasi · LPN · SKU · Lot')
@section('back', route('m.home'))

@section('content')
<button type="button" class="m-bigscan" id="btnScan"><span class="m-bigscan-ring"></span><i class="bi bi-upc-scan"></i><span>Ketuk untuk scan lokasi / LPN / barang</span></button>
<form class="m-scanbar" id="formQ" autocomplete="off">
    <input id="fQ" class="form-control font-monospace text-uppercase" placeholder="atau ketik lokasi / LPN / SKU / lot" enterkeyhint="search">
</form>
<div id="result" class="d-grid gap-2"></div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/scanner.js') }}?v={{ filemtime(public_path('js/scanner.js')) }}"></script>
<script>
(() => {
    const url = @json(route('inventory.lookup'));
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function search(q) {
        q = (q || '').trim(); if (!q) return;
        $('fQ').value = q;
        $('result').innerHTML = '<div class="m-empty"><i class="bi bi-hourglass-split"></i>Mencari...</div>';
        const r = await WMS.api(url, { params: { q } });
        if (!r.ok) { WMSScanner.feedback(false); return $('result').innerHTML = `<div class="m-empty"><i class="bi bi-x-octagon"></i>${esc(r.data.message || 'Gagal mencari.')}</div>`; }
        const rows = r.data.rows;
        WMSScanner.feedback(rows.length > 0);
        if (!rows.length) return $('result').innerHTML = '<div class="m-empty"><i class="bi bi-inbox"></i>Tidak ada stok untuk "' + esc(q) + '".</div>';
        const total = rows.reduce((a, x) => a + parseFloat(String(x.on_hand).replace(/\./g, '').replace(',', '.')), 0);
        $('result').innerHTML = `<div class="small text-muted px-1">${rows.length} baris stok</div>` + rows.map((x) => `
            <div class="m-card">
                <div class="d-flex justify-content-between gap-2">
                    <div class="min-w-0"><b>${esc(x.sku)}</b><div class="small text-muted text-truncate">${esc(x.name)}</div></div>
                    <div class="text-end"><div class="fw-bold">${esc(x.on_hand)} <span class="small fw-normal">${esc(x.uom)}</span></div><div class="small text-muted">tersedia ${esc(x.available)}</div></div>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2 small">
                    <span class="o-dock-chip"><i class="bi bi-geo-alt"></i> <b>${esc(x.location)}</b></span>
                    ${x.lpn ? `<span class="o-dock-chip font-monospace">${esc(x.lpn)}</span>` : ''}
                    ${x.lot ? `<span class="o-dock-chip">Lot ${esc(x.lot)}</span>` : ''}
                    ${x.expiry ? `<span class="o-dock-chip">Exp ${esc(x.expiry)}</span>` : ''}
                    ${x.status_ok ? '' : `<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">${esc(x.status)}</span>`}
                    ${x.allocated !== '0' ? `<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">Alokasi ${esc(x.allocated)}</span>` : ''}
                    ${x.hold !== '0' ? `<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Hold ${esc(x.hold)}</span>` : ''}
                </div>
            </div>`).join('');
    }
    $('formQ').addEventListener('submit', (e) => { e.preventDefault(); search($('fQ').value); });
    $('btnScan').addEventListener('click', () => WMSScanner.open({ title: 'Scan lokasi / LPN / barang', onResult: (code) => search(code) }));
})();
</script>
@endpush
