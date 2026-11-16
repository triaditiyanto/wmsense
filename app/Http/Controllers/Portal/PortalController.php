<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\TenantLogin;
use App\Services\TenantProvisioner;
use App\Support\WarehouseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Portal SaaS di luar WMS (/portal): landing, registrasi site (-> database baru), dan monitoring login
 * (siapa yang sedang online, user paling aktif, jam online harian/bulanan). Monitoring dilindungi
 * password pengelola (PORTAL_PASSWORD di .env), disimpan di session 'portal_admin'.
 */
class PortalController extends Controller
{
    public function landing(): View
    {
        return view('portal.landing', [
            'types' => WarehouseType::cases(),
            'stats' => [
                'sites' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
                'online' => TenantLogin::online()->count(),
                'logins_today' => TenantLogin::whereDate('login_at', today())->count(),
            ],
        ]);
    }

    public function register(): View
    {
        return view('portal.register', ['types' => WarehouseType::cases()]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{2,19}$/', Rule::unique('central.tenant', 'code')],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'warehouse_type' => ['required', Rule::enum(WarehouseType::class)],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_username' => ['required', 'string', 'regex:/^[a-z0-9._-]{3,30}$/'],
            'admin_password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'code.regex' => 'Kode site: 3-20 huruf kecil / angka / - / _ (tanpa spasi).',
            'code.unique' => 'Kode site sudah dipakai.',
            'admin_username.regex' => 'Username: 3-30 huruf kecil / angka / . / - / _.',
        ], ['name' => 'nama site', 'code' => 'kode site', 'warehouse_type' => 'tipe gudang', 'admin_name' => 'nama admin', 'admin_username' => 'username admin', 'admin_password' => 'password']);

        $tenant = $provisioner->provision($data);

        return redirect()->route('portal.registered', $tenant->code);
    }

    public function registered(string $code): View
    {
        $tenant = Tenant::where('code', $code)->firstOrFail();

        return view('portal.registered', ['tenant' => $tenant, 'type' => $tenant->warehouseType()]);
    }

    // ---------------------------------------------------------------- monitoring

    public function monitorLogin(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $request->validate(['password' => ['required']]);
            if (! hash_equals((string) config('portal.password'), (string) $request->password)) {
                return back()->withErrors(['password' => 'Password pengelola salah.']);
            }
            $request->session()->put('portal_admin', true);

            return redirect()->route('portal.monitor');
        }

        return view('portal.monitor-login');
    }

    public function monitorLogout(Request $request): RedirectResponse
    {
        $request->session()->forget('portal_admin');

        return redirect()->route('portal.landing');
    }

    public function monitor(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('portal_admin')) {
            return redirect()->route('portal.monitor.login');
        }
        $date = $request->input('date') ?: today()->format('Y-m-d');
        $month = $request->input('month') ?: today()->format('Y-m');
        $tenantCode = $request->input('tenant');
        $tenants = Tenant::orderBy('name')->get();
        $tenantId = $tenantCode ? $tenants->firstWhere('code', $tenantCode)?->id : null;
        $dur = 'SUM(TIMESTAMPDIFF(SECOND, login_at, COALESCE(logout_at, last_seen_at)))';
        $scope = fn ($q) => $q->when($tenantId, fn ($w) => $w->where('tenant_id', $tenantId));

        $online = $scope(TenantLogin::online()->with('tenant'))->orderByDesc('last_seen_at')->get();
        $perTenant = TenantLogin::online()->selectRaw('tenant_id, COUNT(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');

        // user paling aktif 30 hari terakhir (jumlah login + total jam)
        $top = $scope(TenantLogin::query())->where('login_at', '>=', now()->subDays(30))
            ->selectRaw("tenant_id, username, MAX(user_name) as user_name, COUNT(*) as logins, {$dur} as seconds, MAX(last_seen_at) as last_seen")
            ->groupBy('tenant_id', 'username')->orderByDesc('logins')->orderByDesc('seconds')->limit(15)->get()->each(fn ($r) => $r->setRelation('tenant', $tenants->firstWhere('id', $r->tenant_id)));

        // jam online per user: harian (tanggal dipilih) & bulanan (bulan dipilih)
        $daily = $scope(TenantLogin::query())->whereDate('login_at', $date)
            ->selectRaw("tenant_id, username, MAX(user_name) as user_name, COUNT(*) as logins, {$dur} as seconds, MIN(login_at) as first_login, MAX(COALESCE(logout_at, last_seen_at)) as last_active")
            ->groupBy('tenant_id', 'username')->orderByDesc('seconds')->get()->each(fn ($r) => $r->setRelation('tenant', $tenants->firstWhere('id', $r->tenant_id)));
        $monthly = $scope(TenantLogin::query())->whereRaw("DATE_FORMAT(login_at, '%Y-%m') = ?", [$month])
            ->selectRaw("tenant_id, username, MAX(user_name) as user_name, COUNT(*) as logins, COUNT(DISTINCT DATE(login_at)) as days, {$dur} as seconds")
            ->groupBy('tenant_id', 'username')->orderByDesc('seconds')->get()->each(fn ($r) => $r->setRelation('tenant', $tenants->firstWhere('id', $r->tenant_id)));

        // login per hari 14 hari terakhir (grafik batang sederhana)
        $perDay = $scope(TenantLogin::query())->where('login_at', '>=', today()->subDays(13))
            ->selectRaw('DATE(login_at) as d, COUNT(*) as n, COUNT(DISTINCT CONCAT(tenant_id, ":", username)) as users')
            ->groupBy('d')->get()->keyBy('d');
        $days = collect(range(13, 0))->map(fn ($i) => today()->subDays($i)->format('Y-m-d'))
            ->map(fn ($d) => ['date' => $d, 'n' => (int) ($perDay[$d]->n ?? 0), 'users' => (int) ($perDay[$d]->users ?? 0)]);

        return view('portal.monitor', compact('tenants', 'tenantCode', 'online', 'perTenant', 'top', 'daily', 'monthly', 'days', 'date', 'month') + [
            'totals' => [
                'sites' => $tenants->where('status', Tenant::STATUS_ACTIVE)->count(),
                'online' => $online->count(),
                'logins_today' => $scope(TenantLogin::query())->whereDate('login_at', today())->count(),
                'hours_today' => round(($scope(TenantLogin::query())->whereDate('login_at', today())->selectRaw("{$dur} as s")->value('s') ?? 0) / 3600, 1),
            ],
        ]);
    }
}
