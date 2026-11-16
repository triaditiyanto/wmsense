@extends('portal.layout')
@section('title', 'Daftar site')

@section('content')
<div class="p-panel">
    <h2 class="fw-bold mb-1">Daftar site baru</h2>
    <p class="text-muted">Satu site = satu gudang/perusahaan dengan database sendiri. Login nanti memakai <b>username@kodesite</b>.</p>
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('portal.store') }}" id="regForm">
        @csrf
        <h6 class="fw-bold mt-3">1. Site</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nama site <span class="text-danger">*</span></label><input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Gudang Cikarang" required></div>
            <div class="col-md-6"><label class="form-label">Kode site <span class="text-danger">*</span></label>
                <div class="input-group"><span class="input-group-text">@</span><input name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="cikarang" pattern="[a-z0-9][a-z0-9_-]{2,19}" required></div>
                <div class="form-text">3-20 huruf kecil/angka. Dipakai saat login: <b>username@<span id="codePrev">kodesite</span></b></div>
                @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label">Nama perusahaan</label><input name="company" class="form-control" value="{{ old('company') }}" placeholder="PT ..."></div>
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
            <div class="col-md-3"><label class="form-label">Telepon</label><input name="phone" class="form-control" value="{{ old('phone') }}"></div>
        </div>

        <h6 class="fw-bold mt-4">2. Tipe gudang <span class="text-danger">*</span> <small class="text-muted fw-normal">(menentukan master data awal)</small></h6>
        <div class="p-types">
            @foreach ($types as $t)
                <label class="p-type">
                    <input type="radio" name="warehouse_type" value="{{ $t->value }}" @checked(old('warehouse_type', 'GENERAL') === $t->value)>
                    <div class="p-ic"><i class="bi bi-{{ $t->icon() }}"></i></div>
                    <b>{{ $t->label() }}</b><small>{{ $t->description() }}</small>
                </label>
            @endforeach
        </div>

        <h6 class="fw-bold mt-4">3. Akun admin site</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nama admin <span class="text-danger">*</span></label><input name="admin_name" class="form-control" value="{{ old('admin_name') }}" required></div>
            <div class="col-md-6"><label class="form-label">Username <span class="text-danger">*</span></label>
                <div class="input-group"><input name="admin_username" id="adminUser" class="form-control" value="{{ old('admin_username', 'admin') }}" pattern="[a-z0-9._-]{3,30}" required><span class="input-group-text">@<span id="codePrev2">kodesite</span></span></div>
                @error('admin_username')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="admin_password" class="form-control" minlength="6" required></div>
            <div class="col-md-6"><label class="form-label">Ulangi password <span class="text-danger">*</span></label><input type="password" name="admin_password_confirmation" class="form-control" minlength="6" required></div>
        </div>

        <div class="d-flex align-items-center gap-3 mt-4">
            <button class="btn btn-primary btn-lg px-4" id="btnSubmit"><i class="bi bi-rocket-takeoff me-2"></i>Buat site & database</button>
            <span class="text-muted small">Proses membuat database memakan waktu beberapa detik.</span>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    const code = document.getElementById('code');
    const sync = () => { const v = code.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''); code.value = v; document.querySelectorAll('#codePrev, #codePrev2').forEach((e) => { e.textContent = v || 'kodesite'; }); };
    code.addEventListener('input', sync); sync();
    document.getElementById('adminUser').addEventListener('input', (e) => { e.target.value = e.target.value.toLowerCase(); });
    document.getElementById('regForm').addEventListener('submit', () => { const b = document.getElementById('btnSubmit'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyiapkan database...'; });
</script>
@endpush
