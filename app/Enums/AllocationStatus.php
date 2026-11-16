<?php

namespace App\Enums;

/** Baris alokasi (= baris pick list). OPEN -> PICKED (penuh) | SHORT (kurang / 0) | CANCELLED (dilepas). */
enum AllocationStatus: string
{
    case OPEN = 'OPEN';
    case PICKED = 'PICKED';
    case SHORT = 'SHORT';
    case CANCELLED = 'CANCELLED';

    public function isClosed(): bool
    {
        return $this !== self::OPEN;
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Belum dipick',
            self::PICKED => 'Dipick',
            self::SHORT => 'Kurang',
            self::CANCELLED => 'Dilepas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'primary',
            self::PICKED => 'success',
            self::SHORT => 'warning',
            self::CANCELLED => 'secondary',
        };
    }
}
