<?php

namespace App\Enums;

/** Jenis task gudang. Nomor task memakai prefix(). */
enum TaskType: string
{
    case PUTAWAY = 'PUTAWAY';

    public function prefix(): string
    {
        return match ($this) {
            self::PUTAWAY => 'PTW',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PUTAWAY => 'Putaway',
        };
    }
}
