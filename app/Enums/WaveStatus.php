<?php

namespace App\Enums;

/** Wave: PLANNED ──run──> ALLOCATED ──mulai pick──> PICKING ──> PICKED. Batal hanya sebelum ada yang dipick. */
enum WaveStatus: string
{
    case PLANNED = 'PLANNED';
    case ALLOCATED = 'ALLOCATED';
    case PICKING = 'PICKING';
    case PICKED = 'PICKED';
    case CANCELLED = 'CANCELLED';

    public function canAllocate(): bool
    {
        return in_array($this, [self::PLANNED, self::ALLOCATED, self::PICKING], true);
    }

    public function canPick(): bool
    {
        return in_array($this, [self::ALLOCATED, self::PICKING], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::PLANNED, self::ALLOCATED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Direncanakan',
            self::ALLOCATED => 'Teralokasi',
            self::PICKING => 'Picking',
            self::PICKED => 'Selesai Pick',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLANNED => 'secondary',
            self::ALLOCATED => 'info',
            self::PICKING => 'warning',
            self::PICKED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
