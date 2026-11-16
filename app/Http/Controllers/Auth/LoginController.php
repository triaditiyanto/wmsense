<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Central\TenantLogin;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login multi-site: username ditulis "username@kode_site". Kode site dicari di database pusat (tabel tenant),
 * koneksi DB dialihkan ke database site itu, baru user dicek. Site & sesi disimpan di session supaya request
 * berikutnya (middleware ResolveTenant) memakai database yang sama.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username']);

        [$username, $site] = Tenancy::splitLogin($credentials['username']);
        if (! $site) {
            throw ValidationException::withMessages(['username' => 'Tulis username lengkap dengan kode site: username@kodesite (contoh: admin@demo).']);
        }

        // Batasi 5 percobaan gagal per menit per username+IP (anti brute force)
        $throttleKey = Str::lower($credentials['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages(['username' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."]);
        }

        $tenant = Tenancy::find($site);
        if (! $tenant || $tenant->status !== Tenant::STATUS_ACTIVE) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['username' => $tenant ? "Site {$site} sedang tidak aktif." : "Site \"{$site}\" tidak terdaftar."]);
        }
        Tenancy::use($tenant);

        // Auth::attempt = cari user by username di database site, cek password_verify(), lalu simpan ke session
        if (! Auth::attempt(['username' => $username, 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['username' => 'Username atau password salah, atau user tidak aktif.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate(); // cegah session fixation
        $request->session()->put('tenant_id', $tenant->id);

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        // catatan login di database pusat (monitoring portal)
        $log = TenantLogin::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'username' => $user->username, 'user_name' => $user->name,
            'role' => $user->role?->name, 'session_id' => $request->session()->getId(), 'ip' => $request->ip(),
            'device' => self::isMobile($request) ? 'MOBILE' : 'DESKTOP', 'login_at' => now(), 'last_seen_at' => now(),
        ]);
        $request->session()->put(['tenant_login_id' => $log->id, 'tenant_seen_at' => time()]);

        // Login dari HP / handheld -> langsung ke tampilan mobile operator
        $home = self::isMobile($request) ? route('m.home') : route('dashboard');

        return redirect()->intended($home);
    }

    public static function isMobile(Request $request): bool
    {
        return (bool) preg_match('/Android|iPhone|iPad|iPod|Mobile|Windows CE|Zebra|Honeywell/i', (string) $request->userAgent());
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($id = $request->session()->get('tenant_login_id')) {
            TenantLogin::whereKey($id)->whereNull('logout_at')->update(['logout_at' => now(), 'last_seen_at' => now()]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
