@extends('layouts.mobile')
@section('title', 'Penerimaan')
@section('subtitle', 'Pilih / scan dokumen')
@section('back', route('m.home'))

@use('App\Support\Qty')
@section('content')
<div class="m-scanbar">
    <input id="docSearch" class="form-control" placeholder="Scan / ketik no. receipt atau PO" autocomplete="off" inputmode="search">
    <button class="m-scan-btn" type="button" id="docScan" aria-label="Scan kamera"><i class="bi bi-upc-scan"></i></button>
</div>

<div id="docList" class="d-grid gap-2">
    @forelse ($receipts as $r)
        @php($pct = $r->expected_sum > 0 ? min(100, round($r->received_sum / $r->expected_sum * 100)) : 0)
        <a href="{{ route('m.receiving.show', $r) }}" class="m-card m-doc"
           data-search="{{ strtolower($r->receipt_no.' '.$r->reference_no.' '.$r->senderName()) }}">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold">{{ $r->receipt_no }}</div>
                    <div class="small text-muted text-truncate">{{ $r->senderName() }} @if ($r->reference_no) · {{ $r->reference_no }} @endif</div>
                </div>
                <span class="badge rounded-pill bg-{{ $r->status->color() }}-subtle text-{{ $r->status->color() }}-emphasis">{{ $r->status->label() }}</span>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2">
                <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                <span class="small text-muted">{{ $pct }}%</span>
            </div>
            <div class="small text-muted mt-1">{{ $r->details_count }} item · {{ $r->warehouse->code }} · {{ $r->expected_date?->format('d/m/Y') }}</div>
        </a>
    @empty
        <div class="m-empty"><i class="bi bi-inbox"></i>Tidak ada dokumen yang menunggu penerimaan.</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script src="{{ asset('js/scanner.js') }}?v={{ filemtime(public_path('js/scanner.js')) }}"></script>
<script>
(() => {
    const input = document.getElementById('docSearch');
    const cards = [...document.querySelectorAll('.m-doc')];
    const filter = () => {
        const q = input.value.trim().toLowerCase();
        cards.forEach((c) => c.classList.toggle('d-none', q && !c.dataset.search.includes(q)));
        return cards.filter((c) => !c.classList.contains('d-none'));
    };
    input.addEventListener('input', filter);
    // Enter / hasil scan: bila tinggal satu dokumen yang cocok, langsung buka
    const go = () => { const left = filter(); if (left.length === 1) location.href = left[0].href; };
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); go(); } });
    document.getElementById('docScan').addEventListener('click', () => WMSScanner.open({
        title: 'Scan no. receipt / PO', onResult: (code) => { input.value = code; go(); },
    }));
})();
</script>
@endpush
