<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover: pakai seluruh layar HP (termasuk area notch) --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#007aff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WMSense') · Mobile</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/wms.css') }}?v={{ filemtime(public_path('css/wms.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-flow.css') }}?v={{ filemtime(public_path('css/mobile-flow.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/guide.css') }}?v={{ filemtime(public_path('css/guide.css')) }}">
</head>
<body class="m-body">
@php($me = auth()->user())

<header class="m-appbar">
    @hasSection('back')
        <a href="@yield('back')" class="m-appbar-btn" aria-label="Kembali"><i class="bi bi-chevron-left"></i></a>
    @else
        <span class="m-appbar-logo"><i class="bi bi-boxes"></i></span>
    @endif
    <div class="m-appbar-title">
        <div class="text-truncate">@yield('title', 'WMSense')</div>
        @hasSection('subtitle')<div class="m-appbar-sub text-truncate">@yield('subtitle')</div>@endif
    </div>
    <div class="dropdown">
        <button class="m-appbar-btn" data-bs-toggle="dropdown" aria-label="Menu"><span class="o-avatar">{{ strtoupper(mb_substr($me->name, 0, 1)) }}</span></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-3 py-2 small"><div class="fw-semibold">{{ $me->name }}</div><div class="text-muted">{{ $me->role->name }}</div></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('m.home') }}"><i class="bi bi-grid me-2"></i>Menu utama</a></li>
            <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-display me-2"></i>Tampilan desktop</a></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                </form>
            </li>
        </ul>
    </div>
</header>

<main class="m-main">
    @yield('content')
</main>

{{-- Toast notifikasi --}}
<div class="m-toast-wrap" id="mToast"></div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/wms.js') }}?v={{ filemtime(public_path('js/wms.js')) }}"></script>
<script>
    // Toast ringan untuk mobile: MToast('pesan', 'success'|'danger'|'warning')
    window.MToast = (msg, type = 'success', ms = 2600) => {
        const el = document.createElement('div');
        el.className = `m-toast m-toast-${type}`;
        el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : (type === 'warning' ? 'exclamation-circle-fill' : 'x-circle-fill')}"></i><span></span>`;
        el.querySelector('span').textContent = msg;
        document.getElementById('mToast').append(el);
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, ms);
    };
    @if (session('success')) MToast(@json(session('success'))); @endif
    @if (session('error')) MToast(@json(session('error')), 'danger', 5000); @endif
</script>
@stack('scripts')
</body>
</html>
