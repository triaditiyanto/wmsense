<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') · WMSense</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tom-select/tom-select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/wms.css') }}?v={{ filemtime(public_path('css/wms.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/guide.css') }}?v={{ filemtime(public_path('css/guide.css')) }}">
    @stack('styles')
</head>
<body class="o-app">
@php
    $me = auth()->user();
    $activeItem = \App\Support\Menu::activeItem($menu);
    // Panduan: @section('guide', 'KODE') di view, atau otomatis dari menu yang sedang dibuka
    $guideCode = trim($__env->yieldContent('guide')) ?: ($activeItem?->code ?? (request()->routeIs('dashboard') ? 'HOME' : null));
    $hasGuide = $guideCode && config("guides.{$guideCode}");
@endphp

{{-- ============================== SIDEBAR (desktop) / menu navbar (HP) ==============================
     offcanvas-lg: >= 992px tampil tetap di kiri; < 992px jadi panel yang turun dari navbar atas. --}}
<aside class="o-sidebar offcanvas-lg offcanvas-top" id="oSide" tabindex="-1" aria-label="Menu utama">
    <div class="o-side-head">
        <a href="{{ route('dashboard') }}" class="o-brand"><span class="o-brand-logo"><i class="bi bi-boxes"></i></span><span>WMSense</span></a>
    </div>

    <nav class="o-side-nav" id="oSideNav">
        <a href="{{ route('dashboard') }}" class="o-side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-house-door"></i><span>Beranda</span></a>

        @foreach ($menu as $group)
            @php($open = $activeModule && $activeModule['module']->id === $group['module']->id)
            <div class="o-side-group {{ $open ? 'is-active' : '' }}">
                <button type="button" class="o-side-toggle {{ $open ? '' : 'collapsed' }}" data-bs-toggle="collapse"
                        data-bs-target="#side-{{ $group['module']->code }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                    <span class="o-side-ico" style="background: {{ \App\Support\Menu::color($group['module']->code) }}"><i class="bi bi-{{ $group['module']->icon }}"></i></span>
                    <span class="flex-grow-1 text-start">{{ $group['module']->name }}</span>
                    <i class="bi bi-chevron-down o-side-chev"></i>
                </button>
                {{-- data-bs-parent: accordion, satu modul terbuka dalam satu waktu --}}
                <div id="side-{{ $group['module']->code }}" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#oSideNav">
                    <div class="o-side-items">
                        @foreach ($group['items']->groupBy(fn ($i) => $i->menu_group ?? '') as $groupName => $items)
                            @if ($groupName !== '')<div class="o-side-label">{{ $groupName }}</div>@endif
                            @foreach ($items as $item)
                                <a href="{{ route($item->route_name) }}" class="o-side-link sub {{ \App\Support\Menu::isActive($item->route_name) ? 'active' : '' }}">{{ $item->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </nav>

    <div class="o-side-foot">
        <a href="{{ route('m.home') }}" class="o-side-link"><i class="bi bi-phone"></i><span>Tampilan operator</span></a>
    </div>
</aside>

<div class="o-shell">
    {{-- ============================== TOPBAR (desktop: bar kaca; HP: navbar biru) ============================== --}}
    <header class="o-topbar">
        <button class="o-top-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#oSide" aria-controls="oSide" aria-label="Menu">
            <i class="bi bi-list"></i>
        </button>
        <a href="{{ route('dashboard') }}" class="o-top-brand d-lg-none"><i class="bi bi-boxes"></i> WMSense</a>

        <div class="o-top-title d-none d-lg-flex">
            @if ($activeModule)
                <span class="o-side-ico sm" style="background: {{ \App\Support\Menu::color($activeModule['module']->code) }}"><i class="bi bi-{{ $activeModule['module']->icon }}"></i></span>
                <span class="text-muted">{{ $activeModule['module']->name }}</span>
                @if ($activeItem)<i class="bi bi-chevron-right small text-muted"></i><span>{{ $activeItem->name }}</span>@endif
            @else
                <span>Beranda</span>
            @endif
        </div>

        <div class="ms-auto d-flex align-items-center gap-1">
            @if ($hasGuide)
                <button type="button" class="o-top-btn o-top-help" data-guide-open title="Panduan modul ini"><i class="bi bi-question-circle"></i><span class="d-none d-md-inline">Panduan</span></button>
            @endif

            {{-- Lonceng notifikasi: diisi & di-polling oleh public/js/wms.js --}}
            <div class="dropdown">
                <a href="#" class="o-top-btn position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="notifBell"
                   data-url="{{ route('notifications.index') }}" data-read-all="{{ route('notifications.read-all') }}" aria-label="Notifikasi">
                    <i class="bi bi-bell"></i><span class="o-notif-badge d-none" id="notifCount"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end o-notif-menu">
                    <div class="d-flex align-items-center px-2 py-1">
                        <span class="fw-semibold me-auto">Notifikasi</span>
                        <button type="button" class="btn btn-link btn-sm p-0" id="notifReadAll">Tandai semua dibaca</button>
                    </div>
                    <div id="notifList"><div class="text-muted small p-3 text-center">Memuat...</div></div>
                </div>
            </div>

            <div class="dropdown">
                <a href="#" class="o-top-btn o-top-user" data-bs-toggle="dropdown" aria-label="Akun">
                    <span class="o-avatar">{{ strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                    <span class="d-none d-xl-inline">{{ $me->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px">
                    <li class="px-3 py-2 d-flex align-items-center gap-2">
                        <span class="o-avatar lg">{{ strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                        <div class="lh-sm">
                            <div class="fw-semibold">{{ $me->name }}</div>
                            <div class="small text-muted">{{ '@'.$me->username }} · {{ $me->role->name }}</div>
                            @if ($site = \App\Support\Tenancy::current())<div class="small text-muted"><i class="bi bi-building me-1"></i>{{ $site->name }} ({{ '@'.$site->code }})</div>@endif
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalPassword"><i class="bi bi-key me-2"></i>Ganti Password</button></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>
</div>

{{-- ============================== TOAST (notifikasi) ============================== --}}
<div class="toast-container position-fixed top-0 end-0 p-3">
    @foreach (['success' => ['success', 'check-circle-fill', 4000], 'error' => ['danger', 'exclamation-triangle-fill', 8000]] as $key => [$color, $icon, $delay])
        @if (session($key))
            <div class="toast align-items-center text-bg-{{ $color }} border-0" role="alert" data-bs-delay="{{ $delay }}">
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-{{ $icon }} me-2"></i>{{ session($key) }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- ============================== MODAL GANTI PASSWORD ============================== --}}
<div class="modal fade" id="modalPassword" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content o-input" method="POST" action="{{ route('profile.password') }}" data-ajax>
            @csrf <input type="hidden" name="_method" value="PUT">
            <div class="modal-header"><h5 class="modal-title">Ganti Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-field"><label class="o-required">Password lama</label><input type="password" name="current_password" class="form-control" autocomplete="current-password"></div>
                <div class="o-field"><label class="o-required">Password baru</label><input type="password" name="password" class="form-control" autocomplete="new-password"></div>
                <div class="o-field"><label class="o-required">Ulangi password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>

@stack('modals')

{{-- Panduan animasi modul yang sedang dibuka (config/guides.php) --}}
@if ($hasGuide)<x-guide :code="$guideCode" />@endif

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/tom-select/tom-select.complete.min.js') }}"></script>
<script src="{{ asset('js/wms.js') }}?v={{ filemtime(public_path('js/wms.js')) }}"></script>
<script src="{{ asset('js/m-guide.js') }}?v={{ filemtime(public_path('js/m-guide.js')) }}"></script>
@stack('scripts')
</body>
</html>
