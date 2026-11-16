@extends('layouts.app')
@section('title', 'User')

@section('content')
@php($me = auth()->user())
<div class="o-control-panel d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 me-auto">
        @can('access', ['MST_USER', 'create'])
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalUser"
                    data-action="{{ route('master.users.store') }}" data-method="POST" data-title="User Baru"
                    data-record="{{ json_encode(['is_active' => true]) }}">
                Baru
            </button>
        @endcan
        <div class="o-breadcrumb"><span class="current">User</span></div>
    </div>
    @include('partials.searchbar', [
        'placeholder' => 'Cari username / nama...',
        'filters' => ['role_id' => ['Role', $roles->pluck('name', 'id')->all()]],
    ])
    @include('partials.pager', ['paginator' => $users])
</div>

<div class="o-list table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Email</th><th>Login Terakhir</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            @php($editable = $me->can('access', ['MST_USER', 'edit']) && $me->canManage($u))
            <tr @if ($editable) class="o-row-link" title="Klik untuk mengubah"
                    data-bs-toggle="modal" data-bs-target="#modalUser"
                    data-action="{{ route('master.users.update', $u) }}" data-method="PUT" data-title="{{ $u->name }}"
                    data-record="{{ json_encode($u->only(['username', 'name', 'email', 'role_id', 'is_active'])) }}" @endif>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="o-avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        <span class="fw-semibold">{{ $u->name }}</span>
                        @if ($u->id === $me->id)<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Anda</span>@endif
                    </div>
                </td>
                <td>{{ $u->username }}</td>
                <td>{{ $u->role->name }}</td>
                <td class="text-muted">{{ $u->email }}</td>
                <td class="text-muted">{{ $u->last_login_at?->format('d/m/Y H:i') }}</td>
                <td>
                    @if ($u->is_active)
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis">Aktif</span>
                    @else
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Nonaktif</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="o-empty"><i class="bi bi-people"></i>Tidak ada user yang cocok.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content o-input" method="POST" data-ajax>
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header"><h5 class="modal-title">User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger form-alert d-none"></div>
                <div class="o-group mb-0">
                    <div>
                        <div class="o-field"><label class="o-required">Nama</label><input name="name" class="form-control"></div>
                        <div class="o-field"><label class="o-required">Username</label><input name="username" class="form-control" autocomplete="off"></div>
                        <div class="o-field"><label>Email</label><input type="email" name="email" class="form-control"></div>
                    </div>
                    <div>
                        <div class="o-field">
                            <label class="o-required">Role</label>
                            <div>
                                <select name="role_id" class="form-select">
                                    <option value=""></option>
                                    @foreach ($assignableRoles as $r)<option value="{{ $r->id }}">{{ $r->name }} (L{{ $r->level }})</option>@endforeach
                                </select>
                                <div class="form-text">Hanya role di bawah jabatan Anda.</div>
                            </div>
                        </div>
                        <div class="o-field">
                            <label>Password</label>
                            <div>
                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                                <div class="form-text">Min. 8 karakter. Saat ubah, kosongkan bila tidak diganti.</div>
                            </div>
                        </div>
                        <div class="o-field">
                            <label>Aktif</label>
                            <div>
                                <input type="hidden" name="is_active" value="0">
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_active" value="1"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
        </form>
    </div>
</div>
@endpush
