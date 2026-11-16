@extends('portal.layout')
@section('title', 'Monitoring')

@php
    $hm = fn ($s) => $s >= 3600 ? sprintf('%d j %02d m', intdiv((int) $s, 3600), intdiv((int) $s % 3600, 60)) : sprintf('%d m', intdiv((int) $s, 60));
    $maxDay = max(1, $days->max('n'));
@endphp
@section('content')
<div class="p-section pt-4">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="mb-0 me-auto">Monitoring login</h2>
        <form class="d-flex flex-wrap gap-2 align-items-center" method="GET">
            <select name="tenant" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua site</option>
                @foreach ($tenants as $t)<option value="{{ $t->code }}" @selected($tenantCode === $t->code)>{{ $t->name }} (&#64;{{ $t->code }})</option>@endforeach
            </select>
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()" title="Tanggal untuk tabel harian">
            <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" onchange="this.form.submit()" title="Bulan untuk tabel bulanan">
            <a href="{{ route('portal.monitor') }}" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-clockwise"></i></a>
        </form>
        <form method="POST" action="{{ route('portal.monitor.logout') }}">@csrf<button class="btn btn-sm btn-link text-danger">Keluar</button></form>
    </div>

    <div class="p-stats mb-4" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
        <div class="p-stat"><b>{{ $totals['sites'] }}</b><span>site aktif</span></div>
        <div class="p-stat is-live"><b>{{ $totals['online'] }}</b><span>sedang online (&lt; {{ \App\Models\Central\TenantLogin::ONLINE_MINUTES }} menit)</span></div>
        <div class="p-stat"><b>{{ $totals['logins_today'] }}</b><span>login hari ini</span></div>
        <div class="p-stat"><b>{{ str_replace('.', ',', $totals['hours_today']) }} jam</b><span>total online hari ini</span></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="p-card h-100">
                <h5><span class="p-online me-2"></span>Sedang online ({{ $online->count() }})</h5>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Site</th><th>User</th><th>Role</th><th>Perangkat</th><th>Login</th><th>Terakhir aktif</th><th>Durasi</th></tr></thead>
                    <tbody>
                    @forelse ($online as $o)
                        <tr><td><b>{{ $o->tenant->name }}</b> <span class="text-muted small">&#64;{{ $o->tenant->code }}</span></td>
                            <td>{{ $o->user_name }} <span class="text-muted small">{{ $o->username }}</span></td><td class="small">{{ $o->role }}</td>
                            <td class="small"><i class="bi bi-{{ $o->device === 'MOBILE' ? 'phone' : 'display' }}"></i> {{ $o->device }}</td>
                            <td class="small text-nowrap">{{ $o->login_at->format('d/m H:i') }}</td><td class="small text-nowrap">{{ $o->last_seen_at->diffForHumans() }}</td>
                            <td class="small">{{ $hm($o->durationSeconds()) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada user yang sedang online.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="p-card h-100">
                <h5>Login per hari (14 hari)</h5>
                <div class="p-bars mt-3">
                    @foreach ($days as $d)
                        <div class="p-bar" title="{{ $d['date'] }}: {{ $d['n'] }} login, {{ $d['users'] }} user"><i style="height:{{ round($d['n'] / $maxDay * 100) }}%"></i><span>{{ substr($d['date'], 8) }}</span></div>
                    @endforeach
                </div>
                <h6 class="mt-4">Online per site</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($tenants as $t)
                        <span class="o-dock-chip"><i class="bi bi-building"></i>{{ $t->name }}<b>{{ $perTenant[$t->id] ?? 0 }}</b></span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="p-card">
                <h5>User paling aktif (30 hari)</h5>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Site</th><th>User</th><th class="text-end">Login</th><th class="text-end">Total online</th><th>Terakhir</th></tr></thead>
                    <tbody>
                    @forelse ($top as $i => $r)
                        <tr><td class="text-muted">{{ $i + 1 }}</td><td class="small">{{ $r->tenant?->name }}</td><td>{{ $r->user_name }} <span class="text-muted small">{{ $r->username }}</span></td>
                            <td class="text-end"><b>{{ $r->logins }}</b></td><td class="text-end">{{ $hm($r->seconds) }}</td><td class="small text-nowrap">{{ \Carbon\Carbon::parse($r->last_seen)->format('d/m H:i') }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada login.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="p-card">
                <h5>Jam online harian · {{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</h5>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Site</th><th>User</th><th class="text-end">Login</th><th>Pertama</th><th>Terakhir</th><th class="text-end">Online</th></tr></thead>
                    <tbody>
                    @forelse ($daily as $r)
                        <tr><td class="small">{{ $r->tenant?->name }}</td><td>{{ $r->user_name }}</td><td class="text-end">{{ $r->logins }}</td>
                            <td class="small">{{ \Carbon\Carbon::parse($r->first_login)->format('H:i') }}</td><td class="small">{{ \Carbon\Carbon::parse($r->last_active)->format('H:i') }}</td><td class="text-end"><b>{{ $hm($r->seconds) }}</b></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada login pada tanggal ini.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-12">
            <div class="p-card">
                <h5>Jam online bulanan · {{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</h5>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Site</th><th>User</th><th class="text-end">Login</th><th class="text-end">Hari aktif</th><th class="text-end">Total online</th><th class="text-end">Rata-rata / hari</th></tr></thead>
                    <tbody>
                    @forelse ($monthly as $r)
                        <tr><td class="small">{{ $r->tenant?->name }}</td><td>{{ $r->user_name }} <span class="text-muted small">{{ $r->username }}</span></td><td class="text-end">{{ $r->logins }}</td>
                            <td class="text-end">{{ $r->days }}</td><td class="text-end"><b>{{ $hm($r->seconds) }}</b></td><td class="text-end">{{ $hm($r->days ? $r->seconds / $r->days : 0) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada login pada bulan ini.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-12">
            <div class="p-card">
                <h5>Site terdaftar</h5>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Site</th><th>Kode</th><th>Database</th><th>Tipe</th><th>Admin</th><th>Status</th><th>Dibuat</th></tr></thead>
                    <tbody>
                    @foreach ($tenants as $t)
                        <tr><td><b>{{ $t->name }}</b><div class="small text-muted">{{ $t->company }}</div></td><td><code>{{ $t->code }}</code></td><td class="small">{{ $t->db_name }}</td>
                            <td class="small">{{ $t->warehouseType()?->label() }}</td><td class="small">{{ $t->admin_username }}&#64;{{ $t->code }}</td>
                            <td><span class="badge rounded-pill bg-{{ $t->status === 'ACTIVE' ? 'success' : ($t->status === 'FAILED' ? 'danger' : 'warning') }}-subtle text-{{ $t->status === 'ACTIVE' ? 'success' : ($t->status === 'FAILED' ? 'danger' : 'warning') }}-emphasis">{{ $t->status }}</span>@if ($t->provision_error)<div class="small text-danger">{{ \Illuminate\Support\Str::limit($t->provision_error, 80) }}</div>@endif</td>
                            <td class="small text-nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
@endsection
