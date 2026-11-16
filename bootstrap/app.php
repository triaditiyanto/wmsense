<?php

use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmationRequiredException;
use App\Http\Middleware\EnsureAccess;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

/*
 * Apache + PHP Thread Safe (Laragon/Windows) menjalankan banyak request di SATU proses.
 * putenv() bersifat global per proses dan di-reset PHP di akhir request, sehingga request paralel
 * bisa kehilangan isi .env (gejala acak: 500 "No application encryption key", env = production).
 * Tanpa putenv, .env hanya ditulis ke $_SERVER/$_ENV yang terpisah per request.
 * Di server produksi tetap jalankan `php artisan config:cache`.
 */
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'access' => EnsureAccess::class,
        ]);
        // Multi-tenant: setelah session dibuka, arahkan koneksi DB ke database site yang sedang login
        $middleware->web(append: [ResolveTenant::class]);
        // user yang sudah login lalu membuka /login diarahkan ke dashboard
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Aplikasi diakses lewat reverse proxy HTTPS (Cloudflare Tunnel): percayai header X-Forwarded-*
        // supaya Laravel tahu request aslinya HTTPS (URL asset jadi https://, kamera HP bisa dipakai).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Pelanggaran aturan bisnis bukan error sistem: jangan dicatat di log
        $exceptions->dontReport([BusinessException::class, ConfirmationRequiredException::class]);

        // Butuh konfirmasi (mis. qty melebihi ASN): 409 + daftar peringatan, klien kirim ulang dengan confirm=1
        $exceptions->render(function (ConfirmationRequiredException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'warnings' => $e->warnings,
                'confirm' => true,
                'can_confirm' => $e->canConfirm,
            ], 409);
        });

        // Pelanggaran aturan bisnis -> pesan ramah, bukan halaman error 500
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });
    })->create();
