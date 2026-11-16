<?php

namespace App\Models;

use App\Enums\AccessAction;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Master user. Tabel `users` bawaan Laravel (tidak di-rename). */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['username', 'name', 'email', 'password', 'role_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** Cache hak akses per request: [module_detail_code => RoleAccess] */
    private ?array $accessMap = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed', // otomatis di-hash saat diisi
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role->is_superadmin;
    }

    /**
     * Cek hak akses: $user->hasAccess('INB_RECEIPT', 'create').
     * Dipakai oleh middleware `access` dan Gate `access` (@can di Blade).
     */
    public function hasAccess(string $moduleDetailCode, AccessAction|string $action = AccessAction::VIEW): bool
    {
        if (! $this->is_active || ! $this->role->is_active) {
            return false;
        }
        if ($this->isSuperadmin()) {
            return true;
        }

        $action = $action instanceof AccessAction ? $action : AccessAction::from($action);
        $access = $this->accessMap()[$moduleDetailCode] ?? null;

        return (bool) $access?->{$action->column()};
    }

    /** @return array<string, RoleAccess> */
    private function accessMap(): array
    {
        return $this->accessMap ??= RoleAccess::query()
            ->join('master_module_detail as md', 'md.id', '=', 'master_role_access.module_detail_id')
            ->where('master_role_access.role_id', $this->role_id)
            ->where('md.is_active', true)
            ->get(['master_role_access.*', 'md.code as module_detail_code'])
            ->keyBy('module_detail_code')
            ->all();
    }

    /** Boleh mengelola user lain? Hanya ke bawah (level role lebih besar). */
    public function canManage(User $other): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $other->role->level > $this->role->level && ! $other->role->is_superadmin;
    }

    public function canManageRole(Role $role): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $role->level > $this->role->level && ! $role->is_superadmin;
    }
}
