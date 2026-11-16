<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    protected $table = 'master_supplier';

    protected $fillable = [
        'code', 'name', 'owner_id', 'tax_id', 'pic', 'phone', 'email', 'address', 'city', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /** Supplier yang boleh dipakai owner tsb: milik owner itu ATAU milik bersama (owner_id null). */
    public function scopeForOwner(Builder $query, ?int $ownerId): void
    {
        $query->where(fn ($w) => $w->whereNull('owner_id')->when($ownerId, fn ($q) => $q->orWhere('owner_id', $ownerId)));
    }
}
