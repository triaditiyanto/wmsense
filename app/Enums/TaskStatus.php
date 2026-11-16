<?php

namespace App\Enums;

/** Status task gudang: IN_PROGRESS -> DONE | CANCELLED. */
enum TaskStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'Dikerjakan',
            self::DONE => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'warning',
            self::DONE => 'success',
            self::CANCELLED => 'secondary',
        };
    }
}
