<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Jalan {{ $do->do_no }}</title>
    @use('App\Support\Qty')
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font: 11pt/1.4 "Segoe UI", Arial, sans-serif; color: #111; margin: 0; }
        .sheet { max-width: 182mm; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { margin: 0; font-size: 20pt; letter-spacing: .02em; }
        .head .wh { font-size: 10pt; color: #333; }
        .head .no { text-align: right; }
        .head .no b { font-size: 14pt; font-family: ui-monospace, Consolas, monospace; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 10pt; margin-bottom: 12px; }
        .meta div span { display: inline-block; min-width: 95px; color: #555; }
        .ship { margin-top: 14px; page-break-inside: avoid; }
        .ship-head { display: flex; justify-content: space-between; gap: 12px; background: #f1f3f6; padding: 6px 8px; border: 1px solid #ccc; border-bottom: 0; font-size: 10pt; }
        .ship-head b { font-family: ui-monospace, Consolas, monospace; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; }
        th { background: #fafafa; text-align: left; font-weight: 600; }
        td.r, th.r { text-align: right; }
        tfoot td { font-weight: 600; background: #fafafa; }
        .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 28px; text-align: center; font-size: 10pt; page-break-inside: avoid; }
        .sign div { border-top: 1px solid #111; padding-top: 48px; }
        .foot { margin-top: 10px; font-size: 8.5pt; color: #666; display: flex; justify-content: space-between; }
        .draft { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 60pt; color: rgba(200, 0, 0, .12); transform: rotate(-20deg); pointer-events: none; }
        .bar { position: sticky; top: 0; background: #fff; padding: 8px 0; margin-bottom: 8px; border-bottom: 1px solid #ddd; display: flex; gap: 8px; }
        .bar button { font: inherit; padding: 6px 14px; border-radius: 8px; border: 1px solid #007aff; background: #007aff; color: #fff; cursor: pointer; }
        .bar button.sec { background: #fff; color: #007aff; }
        @media print { .bar { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="bar"><button onclick="window.print()">Cetak / Simpan PDF</button><button class="sec" onclick="window.close()">Tutup</button></div>
    @if ($do->status->value === 'DRAFT')<div class="draft">DRAFT</div>@endif
    <div class="head">
        <div>
            <h1>SURAT JALAN</h1>
            <div class="wh"><b>{{ $do->warehouse->name }}</b> ({{ $do->warehouse->code }})<br>{{ $do->warehouse->address }}</div>
        </div>
        <div class="no">No. <b>{{ $do->do_no }}</b><br>Tgl kirim: {{ $do->ship_date?->format('d/m/Y') ?? '-' }}<br>Status: {{ $do->status->label() }}</div>
    </div>
    <div class="meta">
        <div><span>Kendaraan</span> {{ $do->vehicle_no ?? '-' }}</div>
        <div><span>Ekspedisi</span> {{ $do->carrier ?? '-' }}</div>
        <div><span>Sopir</span> {{ $do->driver_name ?? '-' }}{{ $do->driver_phone ? ' · '.$do->driver_phone : '' }}</div>
        <div><span>Jumlah</span> {{ $do->shipments->count() }} shipment · {{ $totals['lines'] }} baris</div>
        @if ($do->notes)<div style="grid-column:1/-1"><span>Catatan</span> {{ $do->notes }}</div>@endif
    </div>

    @foreach ($do->shipments as $i => $s)
        <div class="ship">
            <div class="ship-head">
                <div><b>{{ $s->shipment_no }}</b>@if ($s->reference_no) · Ref {{ $s->reference_no }}@endif<br><b style="font-family:inherit">{{ $s->ship_to_name }}</b><br>{{ $s->ship_to_address }}</div>
                <div style="text-align:right">Owner: {{ $s->owner->name }}<br>Drop {{ $i + 1 }} dari {{ $do->shipments->count() }}</div>
            </div>
            <table>
                <thead><tr><th style="width:28px">#</th><th style="width:95px">SKU</th><th>Nama barang</th><th class="r" style="width:70px">Qty</th><th style="width:50px">UOM</th><th class="r" style="width:85px">Qty base</th><th style="width:60px">Cek</th></tr></thead>
                <tbody>
                @php($sub = '0')
                @foreach ($s->details as $d)
                    @php($q = $do->status->value === 'DISPATCHED' ? $d->qty_shipped_base : $d->qty_picked_base)
                    @continue(Qty::cmp($q, 0) <= 0)
                    @php($sub = Qty::add($sub, $q))
                    <tr><td>{{ $d->line_no }}</td><td>{{ $d->item->sku }}</td><td>{{ $d->item->name }}</td>
                        <td class="r">{{ Qty::fmt($d->toOrderUom($q)) }}</td><td>{{ $d->uom->code }}</td><td class="r">{{ Qty::fmt($q) }} {{ $d->item->baseUom->code }}</td><td>&#9744;</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="5" class="r">Total {{ $s->shipment_no }}</td><td class="r">{{ Qty::fmt($sub) }}</td><td></td></tr></tfoot>
            </table>
        </div>
    @endforeach

    <div class="sign">
        <div>Disiapkan oleh<br><small>{{ $do->createdBy?->name }}</small></div>
        <div>Sopir / Ekspedisi<br><small>{{ $do->driver_name ?? '&nbsp;' }}</small></div>
        <div>Diterima oleh<br><small>(nama jelas, tanggal, stempel)</small></div>
    </div>
    <div class="foot"><span>Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }} · cetakan ke-{{ $do->print_count }}</span><span>WMSense</span></div>
</div>
</body>
</html>
