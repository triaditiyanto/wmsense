{{--
    Halaman label siap cetak (tanpa layout aplikasi). Ukuran label = ukuran halaman (@page), jadi di dialog print:
    pilih printer label & ukuran kertas yang sama, skala 100% / "Actual size", margin "None".
    ?preview=1 -> hanya label pertama, tanpa toolbar (dipakai iframe pratinjau di Master > Cetak Label).
--}}
@php
    [$sizeName, $w, $h] = $size;
    $isSheet = $opt['size'] === 'a4';
    $list = $preview ? array_slice($labels, 0, 1) : $labels;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label {{ $opt['type'] === 'lpn' ? 'LPN' : 'Lokasi' }} ({{ count($labels) }})</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <style>
        @page { size: {{ $isSheet ? 'A4' : $w.'mm '.$h.'mm' }}; margin: 0; }
        :root { --w: {{ $w }}mm; --h: {{ $h }}mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #000; background: #e9edf3; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* ---------- toolbar layar (tidak ikut tercetak) */
        .bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 16px;
               background: rgba(255,255,255,.85); backdrop-filter: blur(14px); border-bottom: 1px solid #d6dde8; font-size: 14px; }
        .bar b { font-weight: 600; }
        .bar .sp { flex: 1; }
        .bar .hint { color: #5b6577; font-size: 12.5px; }
        .btn { border: 0; border-radius: 10px; padding: 8px 14px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; gap: 6px; align-items: center; }
        .btn-p { background: #007aff; color: #fff; }
        .btn-s { background: #eef1f6; color: #1d2433; }
        .sheet { display: flex; flex-wrap: wrap; gap: 6mm; padding: 8mm; justify-content: center; }
        .sheet.is-a4 { gap: 0; width: 210mm; margin: 6mm auto; padding: .5mm 0 0; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.12); }

        /* ---------- label */
        .lbl { width: var(--w); height: var(--h); background: #fff; overflow: hidden; position: relative; display: flex; flex-direction: column; gap: 1.5mm;
               padding: 2.5mm; box-shadow: 0 2px 10px rgba(0,0,0,.12); border-radius: 1.5mm; break-inside: avoid; }
        .sheet.is-a4 .lbl { box-shadow: none; border-radius: 0; outline: .2mm dashed #cfd6e2; outline-offset: -.1mm; }
        /* atas: strip level + QR + teks. bawah: barcode di baris sendiri selebar label (tidak ada apa pun sejajar barcode,
           syarat decoder kamera zxing-js yang membaca per baris piksel) */
        .lbl-top { flex: 1; min-height: 0; display: flex; gap: 2.5mm; }
        .lbl-main { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: .8mm; overflow: hidden; }
        .lbl-kind { font-size: 2.6mm; font-weight: 700; text-transform: uppercase; letter-spacing: .25mm; color: #333; }
        .lbl-title { font-family: "SF Mono", Consolas, "Roboto Mono", monospace; font-weight: 800; font-size: 6mm; line-height: 1.05; white-space: nowrap; letter-spacing: -.1mm; }
        .lbl-line { font-size: 2.7mm; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lbl-qrbox { flex: none; height: 100%; aspect-ratio: 1; display: flex; align-items: center; }
        .lbl-qrbox svg { display: block; width: 100%; height: 100%; }
        /* barcode: lebar dari atribut style (mm, lebar modul tetap) - jangan ditarik CSS */
        .lbl-barrow { flex: none; display: flex; justify-content: center; }
        .lbl-barrow svg { display: block; height: 12mm; max-width: none; }
        .lbl-bartext { font-family: Consolas, monospace; font-size: 2.6mm; text-align: center; letter-spacing: .4mm; }
        .lbl-level { flex: none; width: 11mm; margin: -2.5mm 0 0 -2.5mm; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; }
        .lbl-level span { font-size: 2.4mm; font-weight: 800; }
        .lbl-level b { font-size: 9mm; line-height: 1; font-weight: 900; }
        .lbl-level.is-light { color: #000; }
        .lbl.no-bar .lbl-level { margin-bottom: -2.5mm; }

        /* pallet tag 100 x 150: susunan tegak */
        .s-pallet { padding: 5mm; gap: 3mm; }
        .s-pallet .lbl-head { display: flex; justify-content: space-between; align-items: baseline; border-bottom: .5mm solid #000; padding-bottom: 1.5mm; }
        .s-pallet .lbl-head .lbl-kind { font-size: 3.4mm; }
        .s-pallet .lbl-title { font-size: 9.5mm; text-align: center; }
        .s-pallet .lbl-qrbox { height: 52mm; align-self: center; }
        .s-pallet.has-bar .lbl-qrbox { height: 40mm; }
        .s-pallet .lbl-barrow { flex-direction: column; align-items: center; }
        .s-pallet .lbl-barrow svg { height: 20mm; }
        .s-pallet .lbl-line { font-size: 3.2mm; text-align: center; }
        .s-pallet .lbl-contents { border-top: .3mm solid #000; padding-top: 1.5mm; font-size: 3mm; flex: 1; min-height: 0; overflow: hidden; }
        .s-pallet .lbl-contents table { width: 100%; border-collapse: collapse; }
        .s-pallet .lbl-contents td { padding: .6mm 0; border-bottom: .2mm solid #bbb; vertical-align: top; }
        .s-pallet .lbl-contents td.q { text-align: right; font-weight: 700; white-space: nowrap; padding-left: 2mm; }
        .s-pallet .lbl-foot { font-size: 2.6mm; color: #333; display: flex; justify-content: space-between; }

        /* label rak 100 x 30 */
        .s-beam { padding: 2mm 2.5mm; gap: 1mm; }
        .s-beam .lbl-level { margin: -2mm 0 0 -2.5mm; width: 13mm; }
        .s-beam.no-bar .lbl-level { margin-bottom: -2mm; }
        .s-beam .lbl-title { font-size: 8mm; }
        .s-beam .lbl-barrow svg { height: 9mm; }
        .s-beam .lbl-line { font-size: 2.5mm; }
        .s-beam.has-bar .lbl-line, .s-beam.has-bar .lbl-kind { display: none; }
        .s-beam.has-bar .lbl-level b { font-size: 6.5mm; }
        .s-beam.has-bar .lbl-title { font-size: 7mm; }

        /* label kecil 50 x 25 */
        .s-small { padding: 1.5mm; gap: 1mm; }
        .s-small .lbl-top { gap: 1.5mm; }
        .s-small .lbl-level { width: 6mm; margin: -1.5mm 0 0 -1.5mm; }
        .s-small.no-bar .lbl-level { margin-bottom: -1.5mm; }
        .s-small .lbl-level span { display: none; }
        .s-small .lbl-level b { font-size: 5mm; }
        .s-small .lbl-title { font-size: 3.6mm; }
        .s-small .lbl-kind, .s-small .lbl-line { font-size: 2mm; }
        .s-small .lbl-barrow svg { height: 10mm; }
        .s-small.has-bar .lbl-line, .s-small.has-bar .lbl-kind { display: none; }
        .s-small.has-bar .lbl-level b { font-size: 4mm; }

        /* A4 70 x 37 */
        .s-a4 { gap: 1mm; }
        .s-a4 .lbl-title { font-size: 4.6mm; }
        .s-a4 .lbl-barrow svg { height: 10mm; }
        .warn { color: #b25f00; font-weight: 600; }

        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { display: block; padding: 0; gap: 0; }
            .sheet.is-a4 { display: flex; width: auto; margin: 0; box-shadow: none; }
            .lbl { box-shadow: none; border-radius: 0; page-break-after: always; break-after: page; }
            .sheet.is-a4 .lbl { page-break-after: auto; break-after: auto; outline: 0; }
        }
        @if ($preview)
            body { background: transparent; }
            .sheet { padding: 0; justify-content: flex-start; }
            .sheet.is-a4 { width: auto; margin: 0; box-shadow: none; background: transparent; }
        @endif
    </style>
</head>
<body>
@unless ($preview)
    <div class="bar">
        <button class="btn btn-p" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
        <a class="btn btn-s" href="{{ $back }}"><i class="bi bi-arrow-left"></i> Kembali</a>
        <span><b>{{ count($labels) }}</b> label · {{ $sizeName }} · {{ \App\Services\LabelService::SYMBOLOGIES[$opt['symbology']] }}</span>
        @if ($barcode_warning)
            <span class="warn"><i class="bi bi-exclamation-triangle-fill"></i> Barcode terlalu panjang untuk ukuran ini (garis &lt; 0,25 mm, sulit di-scan). Pakai QR atau label lebih lebar.</span>
        @endif
        <span class="sp"></span>
        <span class="hint"><i class="bi bi-info-circle"></i> Di dialog cetak: ukuran kertas {{ $isSheet ? 'A4' : $w.' x '.$h.' mm' }}, skala <b>100% / Actual size</b>, margin <b>None</b>.</span>
    </div>
@endunless

<div class="sheet {{ $isSheet ? 'is-a4' : '' }}">
@foreach ($list as $l)
    @php
        $cls = 's-'.$opt['size'].($l['qr'] ? ' has-qr' : ' no-qr').($l['bar'] ? ' has-bar' : ' no-bar');
        $light = $l['level_color'] && in_array($l['level_color'], ['#fdd835'], true);
    @endphp
    @if ($opt['size'] === 'pallet')
        <div class="lbl {{ $cls }}">
            <div class="lbl-head"><span class="lbl-kind">{{ $l['kind'] }}</span><span class="lbl-line">{{ $l['lines'][0] ?? '' }}</span></div>
            <div class="lbl-title" data-fit>{{ $l['title'] }}</div>
            @if ($l['qr'])<div class="lbl-qrbox">{!! $l['qr'] !!}</div>@endif
            @if ($l['bar'])<div class="lbl-barrow">{!! $l['bar'] !!}<div class="lbl-bartext">{{ $l['code'] }}</div></div>@endif
            @foreach (array_slice($l['lines'], 1) as $line)<div class="lbl-line">{{ $line }}</div>@endforeach
            @if ($opt['contents'])
                <div class="lbl-contents">
                    @if ($l['contents'])
                        <table>
                            @foreach ($l['contents'] as $c)
                                <tr><td><b>{{ $c['sku'] }}</b> {{ $c['name'] }}@if ($c['lot'] || $c['exp'])<br>@if ($c['lot'])Lot {{ $c['lot'] }} @endif @if ($c['exp'])· Exp {{ $c['exp'] }}@endif @endif</td><td class="q">{{ $c['qty'] }}</td></tr>
                            @endforeach
                        </table>
                        @if ($l['contents_more'])<div>+{{ $l['contents_more'] }} baris lain</div>@endif
                    @else
                        <div style="color:#666">Isi: kosong (diisi saat receiving)</div>
                    @endif
                </div>
            @endif
            <div class="lbl-foot"><span>WMSense</span><span>Dicetak {{ now()->format('d/m/Y H:i') }}</span></div>
        </div>
    @else
        <div class="lbl {{ $cls }}">
            <div class="lbl-top">
                @if ($l['level_color'])
                    <div class="lbl-level {{ $light ? 'is-light' : '' }}" style="background: {{ $l['level_color'] }}"><span>LEVEL</span><b>{{ $l['level'] }}</b></div>
                @endif
                @if ($l['qr'])<div class="lbl-qrbox">{!! $l['qr'] !!}</div>@endif
                <div class="lbl-main">
                    <div class="lbl-kind">{{ $l['kind'] }}</div>
                    <div class="lbl-title" data-fit>{{ $l['title'] }}</div>
                    @foreach ($opt['size'] === 'beam' ? array_slice($l['lines'], 0, 1) : $l['lines'] as $line)<div class="lbl-line">{{ $line }}</div>@endforeach
                </div>
            </div>
            @if ($l['bar'])<div class="lbl-barrow">{!! $l['bar'] !!}</div>@endif
        </div>
    @endif
@endforeach
</div>

<script>
    // Perkecil teks kode yang terlalu panjang supaya muat satu baris
    document.querySelectorAll('[data-fit]').forEach((el) => {
        let size = parseFloat(getComputedStyle(el).fontSize);
        while (el.scrollWidth > el.clientWidth + 1 && size > 6) { size -= 0.5; el.style.fontSize = size + 'px'; }
    });
    @if (! $preview && request()->boolean('autoprint', true))
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    @endif
</script>
</body>
</html>
