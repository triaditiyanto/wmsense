<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WMSense') · Portal</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/wms.css') }}?v={{ filemtime(public_path('css/wms.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
</head>
<body class="p-body">
<nav class="p-nav">
    <a href="{{ route('portal.landing') }}" class="p-brand"><span class="p-logo"><i class="bi bi-boxes"></i></span>WMSense</a>
    <div class="p-nav-links">
        <a href="{{ route('portal.landing') }}#fitur">Fitur</a>
        <a href="{{ route('portal.landing') }}#tipe">Tipe gudang</a>
        <a href="{{ route('portal.monitor') }}">Monitoring</a>
        <a href="{{ route('login') }}" class="btn btn-sm btn-secondary">Masuk</a>
        <a href="{{ route('portal.register') }}" class="btn btn-sm btn-primary">Daftar site</a>
    </div>
</nav>
<main class="p-main">
    @if (session('error'))<div class="container"><div class="alert alert-danger">{{ session('error') }}</div></div>@endif
    @yield('content')
</main>
<footer class="p-footer">&copy; {{ date('Y') }} WMSense · Warehouse Management System · <a href="{{ route('login') }}">Masuk ke WMS</a></footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
