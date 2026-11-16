@extends('layouts.app')
@section('title', 'Beranda')

@section('content')
<div class="o-home">
    {{-- Grid aplikasi: satu ikon per module yang boleh diakses user --}}
    <div class="o-apps">
        @forelse ($menu as $group)
            <a href="{{ route($group['items']->first()->route_name) }}" class="o-app-tile">
                <div class="o-app-icon" style="background: {{ \App\Support\Menu::color($group['module']->code) }}">
                    <i class="bi bi-{{ $group['module']->icon }}"></i>
                </div>
                {{ $group['module']->name }}
            </a>
        @empty
            <div class="text-muted">Anda belum memiliki akses ke modul apa pun. Hubungi atasan Anda.</div>
        @endforelse
    </div>

    <div class="o-panel mx-auto" style="max-width: 960px">
        <div class="o-panel-title"><i class="bi bi-bar-chart me-1"></i> Ringkasan</div>
        <div class="p-3">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="o-kpi"><div class="o-kpi-value">{{ number_format($itemCount) }}</div><div class="o-kpi-label">Item aktif</div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="o-kpi"><div class="o-kpi-value">{{ number_format($locationCount) }}</div><div class="o-kpi-label">Lokasi</div></div>
                </div>
            </div>

            <div class="small fw-semibold text-uppercase text-muted mb-2">Receipt per status</div>
            <div class="row g-3">
                @php($canReceipt = auth()->user()->can('access', ['INB_RECEIPT', 'view']))
                @foreach ($statuses as $status)
                    <div class="col-6 col-md-4 col-xl-2">
                        <{{ $canReceipt ? 'a' : 'div' }} class="o-kpi" @if ($canReceipt) href="{{ route('inbound.receipts.index', ['status' => $status->value]) }}" @endif>
                            <div class="o-kpi-value">{{ $receiptCounts[$status->value] ?? 0 }}</div>
                            <span class="badge rounded-pill bg-{{ $status->color() }}-subtle text-{{ $status->color() }}-emphasis">{{ $status->label() }}</span>
                        </{{ $canReceipt ? 'a' : 'div' }}>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
