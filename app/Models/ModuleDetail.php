<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Detail menu/hak akses. code dipakai di middleware: access:INB_RECEIPT,create */
class ModuleDetail extends Model
{
    protected $table = 'master_module_detail';

    protected $fillable = ['module_id', 'code', 'name', 'route_name', 'menu_group', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
