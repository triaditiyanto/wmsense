<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di route: ->middleware('access:INB_RECEIPT,create')
 * Parameter setelah ":" dikirim Laravel sebagai argumen tambahan handle().
 */
class EnsureAccess
{
    public function handle(Request $request, Closure $next, string $moduleDetailCode, string $action = 'view'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAccess($moduleDetailCode, $action)) {
            abort(403, 'Anda tidak memiliki hak akses untuk aksi ini.');
        }

        return $next($request);
    }
}
