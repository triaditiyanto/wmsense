<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Response sukses untuk form modal (AJAX). Pesan disimpan di session flash
     * sehingga tampil sebagai alert setelah halaman di-reload / redirect oleh JS.
     */
    protected function ok(string $message, ?string $redirect = null): JsonResponse
    {
        session()->flash('success', $message);

        return response()->json(['message' => $message, 'redirect' => $redirect]);
    }
}
