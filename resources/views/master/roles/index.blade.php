@extends('layouts.app')
@section('title', 'Role & Hak Akses')

@section('content')
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        @can('access', ['MST_ROLE', 'create'])
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRole"
                    data-action="{{ route('master.roles.store') }}" data-method="POST" data-title="Role Baru"
                    data-record="{{ json_encode(['is_active' => true, 'level' => $me->role->level + 1]) }}">
                Baru
            </button>
        @endcan
        <div class="o-breadcrumb"><span class="current">Role &amp; Hak Akses</span></div>
    </div>
    <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Level kecil = jabatan lebih tinggi. Level Anda: <b>{{ $me->role->level }}</b></div>
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th style="width:80px">Level</th><th>Nama</th><th>Kode</th><th>Keterangan</th><th class="text-end">Jumlah User</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($roles as $r)
            @php($editable = ! $r->is_superadmin && $me->can('access', ['MST_ROLE', 'edit']) && $me->canManageRole($r))
            <tr @if ($editable) class="o-row-link" title="Klik untuk mengubah"
                    data-bs-toggle="modal" data-bs-target="#modalRole"
                    data-action="{{ route('master.roles.update', $r) }}" data-method="PUT" data-title="{{ $r->name }}"
                    data-record="{{ json_encode($r->only(['code', 'name', 'level', 'description', 'is_active'])) }}" @endif>
                <td><span class="badge rounded-pill bg-dark-subtle text-dark-emphasis">L{{ $r->level }}</span></td>
                <td class="fw-semibold">
                    {{ $r->name }}
                    @if ($r->is_superadmin)<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis ms-1">Akses penuh</span>@endif
                </td>
                <td>{{ $r->code }}</td>
                <td class="text-muted">{{ $r->description }}</td>
                <td class="text-end">{{ $r->users_count }}</td>
                <td>
                    @if ($r->is_active)
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis">Aktif</span>
                    @else
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Nonaktif</span>
                    @endif
                </td>
                <td class="text-end" onclick="event.stopPropagation()">
                    @unless ($r->is_superadmin)
                        <a href="{{ route('master.roles.access', $r) }}" class="btn btn-sm btn-secondary"><i class="bi bi-shield-lock me-1"></i>Hak Akses</a>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalRole" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content o-input" method="POST" data-ajax>
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header"><h5 class="modal-title">Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-field"><label class="o-required">Nama</label><input name="name" class="form-control"></div>
                <div class="o-field"><label class="o-required">Kode</label><input name="code" class="form-control text-uppercase"></div>
                <div class="o-field">
                    <label class="o-required">Level</label>
                    <div>
                        <input type="number" name="level" class="form-control" min="{{ $me->isSuperadmin() ? 1 : $me->role->level + 1 }}" max="99">
                        <div class="form-text">Harus lebih besar dari level Anda ({{ $me->role->level }}).</div>
                    </div>
                </div>
                <div class="o-field">
                    <label>Aktif</label>
                    <div>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_active" value="1"></div>
                    </div>
                </div>
                <div class="o-field"><label>Keterangan</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endpush
