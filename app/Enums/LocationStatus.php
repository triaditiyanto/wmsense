<?php

namespace App\Enums;

enum LocationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';   // tidak dipakai (mis. rak belum terpasang)
    case BLOCKED = 'BLOCKED';     // diblok sementara (rusak, perbaikan)
    case COUNTING = 'COUNTING';   // sedang stock opname, tidak boleh ada movement

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::INACTIVE => 'Tidak aktif',
            self::BLOCKED => 'Diblok',
            self::COUNTING => 'Sedang dihitung',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::BLOCKED => 'danger',
            self::COUNTING => 'warning',
        };
    }

    public function acceptsMovement(): bool
    {
        return $this === self::ACTIVE;
    }
}
