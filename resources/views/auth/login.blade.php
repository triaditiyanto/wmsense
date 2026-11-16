<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · WMSense</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/wms.css') }}?v={{ filemtime(public_path('css/wms.css')) }}">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(700px 500px at 15% 10%, rgba(0, 122, 255, .35), transparent 60%),
                radial-gradient(600px 500px at 85% 90%, rgba(90, 200, 250, .35), transparent 60%),
                radial-gradient(500px 400px at 80% 15%, rgba(88, 86, 214, .22), transparent 60%),
                #eaf1fc;
        }
        .login-card { max-width: 380px; width: 100%; border-radius: 26px !important; background: rgba(255, 255, 255, .55) !important; }
        .login-logo {
            width: 64px; height: 64px; border-radius: 18px; background: var(--o-brand-grad); color: #fff; font-size: 2rem;
            display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 12px 26px -10px rgba(0, 90, 220, .8);
            position: relative; overflow: hidden;
        }
        .login-logo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.35), rgba(255,255,255,0) 55%); }
        .login-card .form-control { background: rgba(255, 255, 255, .7); border-color: rgba(15, 35, 75, .1); padding: .6rem .85rem; }
        .login-card .form-control:focus { background: #fff; }
    </style>
</head>
<body class="d-flex flex-column align-items-center justify-content-center p-3">
    <div class="text-center mb-4">
        <div class="login-logo mb-2"><i class="bi bi-boxes"></i></div>
        <h1 class="h4 fw-bold mb-0">WMSense</h1>
        <div class="text-muted small">Warehouse Management System</div>
    </div>

    @if (session('error'))<div class="alert alert-danger small py-2" style="max-width:380px;width:100%">{{ session('error') }}</div>@endif
    <div class="o-panel login-card p-4">
        {{-- Form biasa (bukan AJAX): error validasi dikirim balik lewat $errors, input lama lewat old() --}}
        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" for="username">Username<span class="text-muted fw-normal">&#64;kodesite</span></label>
                <input type="text" name="username" id="username" value="{{ old('username') }}" placeholder="admin@demo"
                       class="form-control @error('username') is-invalid @enderror" autofocus autocomplete="username">
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="password">Password</label>
                <input type="password" name="password" id="password"
                       class="form-control @error('password') is-invalid @enderror" autocomplete="current-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
            <button class="btn btn-primary w-100 py-2">Masuk</button>
        </form>
        <div class="text-center small mt-3 text-muted">Belum punya site? <a href="{{ route('portal.register') }}">Daftar site baru</a> · <a href="{{ route('portal.landing') }}">Portal</a></div>
    </div>
    <div class="text-muted small mt-4">&copy; {{ date('Y') }} WMSense</div>
</body>
</html>
