<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Header menu/hak akses. */
class Module extends Model
{
    protected $table = 'master_module';

    protected $fillable = ['code', 'name', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ModuleDetail::class)->orderBy('sort_order');
    }
}
