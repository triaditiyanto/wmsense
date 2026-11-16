{{--
    Pustaka adegan animasi untuk panduan (CSS di public/css/guide.css, kelas gs-* / g-*).
    Pakai: <x-guide-scene :slide="['scene' => 'hierarchy', 'opts' => [...]]" />
    Aturan: gaya dasar = keadaan akhir; animasi hanya "from" -> aman untuk prefers-reduced-motion.
--}}
@props(['slide'])
@php($o = $slide['opts'] ?? [])
<div class="g-scene g-scene--{{ $slide['scene'] }}">
@switch($slide['scene'])
    @case('nav')
        <div class="gs-win">
            <div class="gs-side"><span class="gs-logo"></span><span class="gs-sl on"></span><div class="gs-sub"><i></i><i></i><i></i></div><span class="gs-sl"></span><span class="gs-sl"></span></div>
            <div class="gs-body"><span class="gs-bar"></span><span class="gs-line"></span><span class="gs-line"></span><span class="gs-line"></span><span class="gs-line"></span></div>
        </div>
        <i class="bi bi-cursor-fill gs-cursor"></i>
        @break

    @case('list')
        <div class="gs-win gs-win-list">
            <div class="gs-search"><i class="bi bi-search"></i>@php($q = $o['q'] ?? 'cari')<span class="g-type" style="--w: {{ mb_strlen($q) + 0.6 }}ch">{{ $q }}</span></div>
            <div class="gs-row"><b></b><span></span><em></em></div>
            <div class="gs-row hit"><b></b><span></span><em></em></div>
            <div class="gs-row"><b></b><span></span><em></em></div>
            <div class="gs-row"><b></b><span></span><em></em></div>
        </div>
        @break

    @case('form')
        <div class="gs-win gs-win-list gs-dim">
            <div class="gs-row"><b></b><span></span><em></em></div><div class="gs-row"><b></b><span></span><em></em></div>
            <div class="gs-row"><b></b><span></span><em></em></div><div class="gs-row"><b></b><span></span><em></em></div>
        </div>
        <div class="gs-modal">
            <div class="gs-mtitle">{{ $o['title'] ?? 'Data baru' }}</div>
            <div class="gs-field"><span></span></div><div class="gs-field"><span></span></div><div class="gs-field"><span></span></div>
            <div class="gs-btn">Simpan</div>
        </div>
        <div class="g-ok gs-ok"><i class="bi bi-check-lg"></i></div>
        @break

    @case('hierarchy')
        @php($labels = $o['labels'] ?? ['Group', 'Class', 'Item'])
        @php($icons = $o['icons'] ?? ['collection', 'tags', 'box-seam'])
        <div class="gs-tree">
            <div class="gs-node n1"><i class="bi bi-{{ $icons[0] }}"></i>{{ $labels[0] }}</div>
            <div class="gs-link l1"></div>
            <div class="gs-node n2"><i class="bi bi-{{ $icons[1] }}"></i>{{ $labels[1] }}</div>
            <div class="gs-link l2"></div>
            <div class="gs-node n3"><i class="bi bi-{{ $icons[2] }}"></i>{{ $labels[2] }}</div>
        </div>
        @break

    @case('matrix')
        @php($on = [[1, 1, 1, 0, 0], [1, 1, 1, 1, 1], [1, 0, 0, 0, 0], [1, 1, 0, 0, 0]])
        <div class="gs-matrix">
            <span></span>@foreach (['L', 'T', 'U', 'H', 'S'] as $h)<span class="gs-mh">{{ $h }}</span>@endforeach
            @foreach (['Item', 'Receipt', 'User', 'Lokasi'] as $r => $row)
                <span class="gs-mr">{{ $row }}</span>
                @foreach ($on[$r] as $c => $v)
                    <i class="{{ $v ? 'on' : '' }}" style="--d: {{ 0.4 + ($r * 5 + $c) * 0.07 }}s"></i>
                @endforeach
            @endforeach
        </div>
        @break

    @case('roles')
        <div class="gs-roles">
            @foreach ([['Manager', 'person-workspace', 1], ['SPV', 'person-badge', 2], ['Admin', 'person-gear', 3], ['Operator', 'person', 4]] as [$name, $icon, $lv])
                <div class="gs-role r{{ $lv }}"><i class="bi bi-{{ $icon }}"></i>{{ $name }} <small>L{{ $lv }}</small></div>
            @endforeach
            <div class="gs-arrow"></div>
        </div>
        @break

    @case('capacity')
        <div class="gs-rack">
            <div class="gs-slot s-ok" style="--h: 30%; --d: .3s"><span>1/4</span><b></b></div>
            <div class="gs-slot s-mid" style="--h: 75%; --d: .6s"><span>3/4</span><b></b></div>
            <div class="gs-slot s-full" style="--h: 100%; --d: .9s"><span>4/4</span><b></b></div>
        </div>
        <div class="gs-full-tag">PENUH → dialihkan</div>
        @break

    @case('scan')
        @php($target = $o['target'] ?? 'tag')
        @if ($target === 'pallet')
            <div class="g-target g-pallet">
                <div class="g-boxes"><i class="bi bi-box-seam-fill"></i><i class="bi bi-box-seam-fill"></i><i class="bi bi-box-seam-fill"></i></div>
                <div class="g-pallet-base"></div>
                <div class="g-sticker"><div class="g-barcode"></div><span>LPN-000123</span></div>
            </div>
        @elseif ($target === 'carton')
            <div class="g-target g-carton"><i class="bi bi-box2-fill"></i><div class="g-barcode g-barcode-sm"></div></div>
            <div class="g-beep"></div>
        @else
            <div class="g-floor"></div>
            <div class="g-target g-tag"><div class="g-barcode"></div><span>{{ $o['code'] ?? 'RCV-01' }}</span></div>
        @endif
        <div class="g-phone"><div class="g-phone-screen"><div class="g-laser"></div></div></div>
        <div class="g-ok"><i class="bi bi-check-lg"></i></div>
        @break

    @case('truck')
        <div class="g-road"></div>
        <div class="g-truck"><i class="bi bi-truck"></i></div>
        <div class="g-drop g-drop-1"><i class="bi bi-box-seam-fill"></i></div>
        <div class="g-drop g-drop-2"><i class="bi bi-box-seam-fill"></i></div>
        <div class="g-drop g-drop-3"><i class="bi bi-box-seam-fill"></i></div>
        @break

    @case('doc')
        <div class="gs-doc">
            <div class="gs-cap">HEADER</div>
            <div class="gs-doc-h">
                <b>RCV-WH1-0002</b>
                <span class="gs-pills"><em class="p1">Draft</em><em class="p2">Open</em><em class="p3">Receiving</em></span>
            </div>
            <div class="gs-cap">DETAIL</div>
            <div class="gs-doc-l"><i></i><span></span><em>24</em></div>
            <div class="gs-doc-l"><i></i><span></span><em>50</em></div>
            <div class="gs-doc-l"><i></i><span></span><em>12</em></div>
        </div>
        @break

    @case('dock')
        <div class="g-floor"></div>
        <div class="gs-pallets">
            <div class="gs-plt"><i class="bi bi-box-seam-fill"></i><b></b></div>
            <div class="gs-plt"><i class="bi bi-box-seam-fill"></i><b></b></div>
            <div class="gs-plt"><i class="bi bi-box-seam-fill"></i><b></b></div>
        </div>
        <div class="gs-clock"><i></i></div>
        <div class="gs-age">3 jam di dock</div>
        <div class="gs-dash"></div>
        <div class="gs-goal"><i class="bi bi-grid-3x3"></i>Rak</div>
        @break

    @case('undo')
        <div class="gs-docmini"><i class="bi bi-file-earmark-text"></i><span>Receiving</span><em>qty kembali</em></div>
        <div class="gs-dockmini">DOCK</div>
        <div class="gs-move"><i class="bi bi-box-seam-fill"></i></div>
        <div class="gs-undo"><i class="bi bi-arrow-counterclockwise"></i></div>
        @break

    @case('partners')
        <div class="gs-partners">
            <div class="gs-p p1"><i class="bi bi-truck"></i><span>Supplier</span></div>
            <div class="gs-path"><b></b></div>
            <div class="gs-p p2"><i class="bi bi-buildings"></i><span>Gudang</span></div>
            <div class="gs-path"><b></b></div>
            <div class="gs-p p3"><i class="bi bi-shop"></i><span>Customer</span></div>
        </div>
        @break

    @case('uom')
        <div class="gs-uom">
            <div class="gs-ctn"><i class="bi bi-box2-fill"></i><b>1 CTN</b></div>
            <span class="gs-eq">=</span>
            <div class="gs-pcs">
                <div class="gs-dots">@for ($i = 0; $i < 24; $i++)<i style="--d: {{ 0.6 + $i * 0.04 }}s"></i>@endfor</div>
                <b><span data-count-to="24">24</span> PCS</b>
            </div>
        </div>
        @break

    @case('toggle')
        <div class="gs-win gs-win-list gs-toggle">
            <div class="gs-trow"><b></b><span></span><div class="gs-switch"><i></i></div></div>
            <div class="gs-trow off"><b></b><span></span><div class="gs-switch is-off"><i></i></div><em>Nonaktif</em></div>
            <div class="gs-trow"><b></b><span></span><div class="gs-switch"><i></i></div></div>
        </div>
        @break

    @case('putaway')
        <div class="gs-dockmini gs-pt-dock">DOCK</div>
        <div class="gs-rack2">
            @for ($i = 0; $i < 6; $i++)
                <div class="gs-cell {{ $i === 1 ? 'is-target' : ($i === 3 || $i === 5 ? 'is-full' : '') }}">
                    @if ($i === 1)<div class="gs-pt-move"><i class="bi bi-box-seam-fill"></i><b></b></div>@endif
                    @if ($i === 3 || $i === 5)<i class="bi bi-box-seam-fill gs-cell-box"></i>@endif
                </div>
            @endfor
        </div>
        <div class="gs-pt-tag"><i class="bi bi-lightbulb-fill"></i> Saran: {{ $o['code'] ?? 'A-01-02-1' }}</div>
        <div class="g-ok gs-ok-pt"><i class="bi bi-check-lg"></i></div>
        @break

    @case('pivot')
        <div class="gs-pivot">
            @php($heat = [[.2, .5, .9], [.4, .15, .6], [.8, .3, .1]])
            <span class="h"></span><span class="h">Baik</span><span class="h">Rusak</span><span class="h">Total</span>
            @foreach (['Sen', 'Sel', 'Rab'] as $r => $day)
                <span class="rh">{{ $day }}</span>
                @foreach ($heat[$r] as $c => $v)
                    <span class="v {{ $c === 2 ? 't' : '' }}" style="--a: {{ $v }}; --d: {{ 0.4 + ($r * 3 + $c) * 0.12 }}s"></span>
                @endforeach
            @endforeach
        </div>
        @break

    @case('chart')
        <div class="gs-chart">
            @foreach ([45, 70, 35, 90, 60] as $i => $h)
                <div class="gs-bar" style="--h: {{ $h }}%; --d: {{ 0.3 + $i * 0.15 }}s"></div>
            @endforeach
        </div>
        @break

    @case('excel')
        <div class="gs-cols">
            @foreach (['SKU', 'Qty', 'Lot', 'Dock'] as $i => $c)
                <div class="gs-colchk {{ $i === 2 ? 'off' : '' }}" style="--d: {{ 0.3 + $i * 0.2 }}s"><i></i>{{ $c }}</div>
            @endforeach
        </div>
        <div class="gs-arrow2"><i class="bi bi-arrow-right"></i></div>
        <div class="gs-xlsx"><i class="bi bi-file-earmark-excel-fill"></i><span>.xlsx</span></div>
        @break

    @case('qr')
        {{-- label QR di rak + HP yang men-scan --}}
        @php($qr = ['1111111010111', '1000001001101', '1011101011001', '1011101010111', '1011101001001', '1000001011011', '1111111010101', '0000000011000', '1101011100110', '0110100101011', '1011011010001', '0001101101101', '1110101011011'])
        <div class="gs-qrlabel">
            <div class="gs-qr">@foreach ($qr as $row)@foreach (str_split($row) as $b)<i class="{{ $b === '1' ? 'on' : '' }}"></i>@endforeach @endforeach</div>
            <div class="gs-qrtext"><b>{{ $o['code'] ?? 'A-01-02-1' }}</b><span>LEVEL 1</span></div>
        </div>
        <div class="gs-phone"><div class="gs-phone-screen"><span class="gs-phone-scan"></span><i class="bi bi-qr-code-scan"></i></div></div>
        <div class="g-ok gs-ok-qr"><i class="bi bi-check-lg"></i></div>
        @break

    @case('rack3d')
        {{-- denah isometrik: 3 baris rak berisi pallet --}}
        <div class="gs-iso">
            <div class="gs-iso-floor">
                @foreach ([[1, 1, 0, 1, 2, 1], [0, 1, 1, 3, 0, 1], [1, 0, 2, 1, 1, 0]] as $r => $row)
                    <div class="gs-iso-row">
                        @foreach ($row as $c => $v)
                            <span class="gs-iso-box v{{ $v }}" style="--d: {{ 0.5 + ($r * 6 + $c) * 0.05 }}s"></span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="gs-iso-pin"><i class="bi bi-geo-alt-fill"></i></div>
        @break

    @case('dnd')
        {{-- area diseret dari palet ke daftar prioritas --}}
        <div class="gs-dnd-list">
            <div class="gs-dnd-row" style="--c:#007aff"><b>1</b>Aisle 02 · L1-2</div>
            <div class="gs-dnd-row" style="--c:#34c759"><b>2</b>Aisle 01</div>
            <div class="gs-dnd-slot"></div>
        </div>
        <div class="gs-dnd-pal">
            <span>Zona A</span><span class="is-drag">Aisle 03</span><span>Kelas Rak</span>
        </div>
        <div class="gs-dnd-hand"><i class="bi bi-hand-index-thumb-fill"></i></div>
        @break

    @case('print')
        <div class="gs-printer"><i class="bi bi-printer-fill"></i></div>
        <div class="gs-label"><div class="g-barcode"></div><span>LPN-WH1-26-000001</span></div>
        <div class="g-ok gs-ok-print"><i class="bi bi-check-lg"></i></div>
        @break
@endswitch
</div>
