<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();

        $users = User::query()
            ->with('role')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->input('q');
                $query->where(fn ($w) => $w->where('users.username', 'like', "%{$q}%")->orWhere('users.name', 'like', "%{$q}%"));
            })
            ->when($request->filled('role_id'), fn ($query) => $query->where('users.role_id', $request->integer('role_id')))
            ->join('master_role', 'master_role.id', '=', 'users.role_id')
            ->orderBy('master_role.level')->orderBy('users.username')
            ->select('users.*')
            ->paginate(20)
            ->withQueryString();

        return view('master.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('level')->get(),
            'assignableRoles' => Role::manageableBy($me)->where('is_active', true)->orderBy('level')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        User::create($data);

        return $this->ok("User {$data['username']} berhasil dibuat.");
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canManage($user), 403, 'Anda tidak boleh mengubah user dengan jabatan setara atau lebih tinggi.');

        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']); // kosong = password tidak diganti
        }
        $user->update($data);

        return $this->ok("User {$user->username} berhasil diperbarui.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $assignableRoleIds = Role::manageableBy($request->user())->where('is_active', true)->pluck('id');

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role_id' => ['required', Rule::in($assignableRoleIds)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'is_active' => ['boolean'],
        ], [
            'role_id.in' => 'Role tidak valid atau di atas wewenang Anda.',
        ], [
            'role_id' => 'role',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
