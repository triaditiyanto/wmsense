@extends('portal.layout')
@section('title', 'Site siap')

@section('content')
<div class="p-panel text-center" style="max-width:640px">
    <div class="p-logo mx-auto mb-3" style="width:64px;height:64px;font-size:2rem;border-radius:18px"><i class="bi bi-check2"></i></div>
    <h2 class="fw-bold">Site <b>{{ $tenant->name }}</b> siap dipakai</h2>
    <p class="text-muted">Database <code>{{ $tenant->db_name }}</code> sudah dibuat dan diisi master data contoh untuk tipe <b>{{ $type?->label() }}</b>.</p>
    <div class="my-4">
        <div class="small text-muted mb-1">Masuk dengan</div>
        <div class="p-login-code">{{ $tenant->admin_username }}&#64;{{ $tenant->code }}</div>
        <div class="small text-muted mt-2">dan password yang Anda buat saat registrasi.</div>
    </div>
    <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-5"><i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke WMS</a>
    <div class="text-start mt-4 small text-muted">
        <b>Langkah berikutnya di dalam WMS:</b>
        <ol class="mb-0 ps-3">
            <li>Master &gt; Gudang &amp; Zona: ubah nama/alamat gudang dan zona.</li>
            <li>Master &gt; Lokasi: generate rak sesuai denah Anda; Cetak Label untuk label lokasi & LPN.</li>
            <li>Master &gt; Item: ganti item contoh dengan barang Anda (import bisa lewat tambah satu per satu).</li>
            <li>Master &gt; User: tambahkan admin dokumen & operator (login mereka juga <code>username&#64;{{ $tenant->code }}</code>).</li>
        </ol>
    </div>
</div>
@endsection
