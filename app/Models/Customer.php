<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Customer (header) + alamat kirim (detail: CustomerAddress). */
class Customer extends Model
{
    protected $table = 'master_customer';

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

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('label');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    /** Customer yang boleh dipakai owner tsb: milik owner itu ATAU milik bersama (owner_id null). */
    public function scopeForOwner(Builder $query, ?int $ownerId): void
    {
        $query->where(fn ($w) => $w->whereNull('owner_id')->when($ownerId, fn ($q) => $q->orWhere('owner_id', $ownerId)));
    }
}
