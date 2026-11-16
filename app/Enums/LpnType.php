<?php

namespace App\Enums;

enum LpnType: string
{
    case PALLET = 'PALLET';
    case CARTON = 'CARTON';
    case TOTE = 'TOTE';     // keranjang / box plastik
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::PALLET => 'Pallet',
            self::CARTON => 'Karton',
            self::TOTE => 'Tote / Keranjang',
            self::OTHER => 'Lainnya',
        };
    }
}
