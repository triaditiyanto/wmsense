<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'master_role';

    protected $fillable = ['code', 'name', 'level', 'is_superadmin', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_superadmin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(RoleAccess::class);
    }

    /**
     * Query scope: Role::manageableBy($user)->get().
     * Method scopeXxx otomatis jadi ->xxx() di query builder.
     */
    public function scopeManageableBy(Builder $query, User $user): void
    {
        if (! $user->role->is_superadmin) {
            $query->where('level', '>', $user->role->level)->where('is_superadmin', false);
        }
    }
}
