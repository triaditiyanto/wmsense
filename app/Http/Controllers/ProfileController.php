<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'current_password' => 'password lama',
            'password' => 'password baru',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return $this->ok('Password berhasil diganti.');
    }
}
