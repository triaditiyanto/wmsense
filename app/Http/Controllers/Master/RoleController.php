<?php

namespace App\Http\Controllers\Master;

use App\Enums\AccessAction;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use App\Models\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        return view('master.roles.index', [
            'roles' => Role::withCount('users')->orderBy('level')->get(),
            'me' => $request->user(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        Role::create($data);

        return $this->ok("Role {$data['name']} berhasil dibuat.");
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->canManageRole($role), 403, 'Anda tidak boleh mengubah role setara atau lebih tinggi.');
        abort_if($role->is_superadmin, 403, 'Role superadmin tidak bisa diubah.');

        $role->update($this->validated($request, $role));

        return $this->ok("Role {$role->name} berhasil diperbarui.");
    }

    /** Halaman matriks hak akses: Module (header) -> Module Detail (detail) x aksi. */
    public function access(Request $request, Role $role): View
    {
        return view('master.roles.access', [
            'role' => $role,
            'modules' => Module::with('details')->where('is_active', true)->orderBy('sort_order')->get(),
            'accesses' => $role->accesses()->get()->keyBy('module_detail_id'),
            'actions' => AccessAction::cases(),
            'canEdit' => $request->user()->canManageRole($role)
                && ! $role->is_superadmin
                && $request->user()->hasAccess('MST_ROLE', AccessAction::EDIT),
            'me' => $request->user(),
        ]);
    }

    public function saveAccess(Request $request, Role $role): JsonResponse
    {
        $me = $request->user();
        abort_unless($me->canManageRole($role), 403, 'Anda tidak boleh mengubah hak akses role setara atau lebih tinggi.');
        abort_if($role->is_superadmin, 403, 'Superadmin otomatis punya semua akses.');

        $input = $request->validate(['access' => ['array']])['access'] ?? [];
        $modules = Module::with('details')->get();
        $existing = $role->accesses()->get()->keyBy('module_detail_id');

        DB::transaction(function () use ($modules, $existing, $input, $me, $role) {
            foreach ($modules as $module) {
                foreach ($module->details as $detail) {
                    $values = [];
                    foreach (AccessAction::cases() as $action) {
                        $col = $action->column();
                        // Tidak boleh memberi akses yang dirinya sendiri tidak punya:
                        // kalau begitu, nilai lama dipertahankan.
                        $values[$col] = $me->hasAccess($detail->code, $action)
                            ? ! empty($input[$detail->id][$action->value])
                            : (bool) $existing->get($detail->id)?->{$col};
                    }

                    RoleAccess::updateOrCreate(
                        ['role_id' => $role->id, 'module_detail_id' => $detail->id],
                        $values,
                    );
                }
            }
        });

        return $this->ok("Hak akses role {$role->name} berhasil disimpan.");
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $me = $request->user();
        $minLevel = $me->isSuperadmin() ? 1 : $me->role->level + 1;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_role', 'code')->ignore($role)],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', "min:{$minLevel}", 'max:99'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ], [
            'level.min' => "Level minimal {$minLevel} (harus di bawah jabatan Anda).",
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
