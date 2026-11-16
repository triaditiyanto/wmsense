<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sesi login user tenant (database pusat). last_seen_at diperbarui tiap request (maks 1x / menit).
 * "Sedang online" = belum logout dan terakhir terlihat < ONLINE_MINUTES menit lalu.
 */
class TenantLogin extends Model
{
    public const ONLINE_MINUTES = 5;

    protected $connection = 'central';

    protected $table = 'tenant_login';

    protected $fillable = ['tenant_id', 'user_id', 'username', 'user_name', 'role', 'session_id', 'ip', 'device', 'login_at', 'last_seen_at', 'logout_at'];

    protected function casts(): array
    {
        return ['login_at' => 'datetime', 'last_seen_at' => 'datetime', 'logout_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->whereNull('logout_at')->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES));
    }

    /** Lama sesi (detik): sampai logout, atau sampai terakhir terlihat. */
    public function durationSeconds(): int
    {
        return max(0, ($this->logout_at ?? $this->last_seen_at)->diffInSeconds($this->login_at, true));
    }
}
