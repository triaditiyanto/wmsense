<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Models\Central\TenantLogin;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang di grup 'web' SETELAH session dibuka: baca tenant dari session (diisi saat login username@site),
 * arahkan koneksi database ke database tenant, lalu perbarui "terakhir terlihat" di catatan login pusat
 * (maks 1x per menit). Request tanpa tenant di session (portal, halaman login) memakai database default .env.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->hasSession() ? $request->session() : null;
        $tenantId = $session?->get('tenant_id');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant || $tenant->status !== Tenant::STATUS_ACTIVE) {
                // site dihapus / ditangguhkan -> paksa keluar
                $session->invalidate();

                return redirect()->route('login')->with('error', 'Site tidak aktif. Hubungi pengelola.');
            }
            Tenancy::use($tenant);
            $this->heartbeat($request);
        }

        return $next($request);
    }

    private function heartbeat(Request $request): void
    {
        $session = $request->session();
        $loginId = $session->get('tenant_login_id');
        if (! $loginId || ! Auth::check()) {
            return;
        }
        $last = (int) $session->get('tenant_seen_at', 0);
        if (time() - $last < 60) {
            return;
        }
        $session->put('tenant_seen_at', time());
        TenantLogin::whereKey($loginId)->whereNull('logout_at')->update(['last_seen_at' => now()]);
    }
}
