<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAccess extends Model
{
    protected $table = 'master_role_access';

    protected $fillable = [
        'role_id', 'module_detail_id',
        'can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
            'can_approve' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function moduleDetail(): BelongsTo
    {
        return $this->belongsTo(ModuleDetail::class);
    }
}
