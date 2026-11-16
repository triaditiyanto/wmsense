@extends('portal.layout')
@section('title', 'Monitoring')

@section('content')
<div class="p-panel" style="max-width:420px">
    <h4 class="fw-bold">Monitoring portal</h4>
    <p class="text-muted small">Halaman pengelola: siapa yang sedang login, user paling aktif, jam online. Masukkan password pengelola (<code>PORTAL_PASSWORD</code> di .env).</p>
    <form method="POST" action="{{ route('portal.monitor.login') }}">
        @csrf
        <input type="password" name="password" class="form-control form-control-lg mb-2 @error('password') is-invalid @enderror" placeholder="Password pengelola" autofocus>
        @error('password')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <button class="btn btn-primary w-100">Buka monitoring</button>
    </form>
</div>
@endsection
