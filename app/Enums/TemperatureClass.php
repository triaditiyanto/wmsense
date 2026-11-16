<?php

namespace App\Enums;

enum TemperatureClass: string
{
    case AMBIENT = 'AMBIENT';
    case AIR_CONDITIONED = 'AIR_CONDITIONED'; // ~18-25°C
    case CHILLED = 'CHILLED';                 // 2-8°C
    case FROZEN = 'FROZEN';                   // <= -18°C

    public function label(): string
    {
        return match ($this) {
            self::AMBIENT => 'Suhu ruang',
            self::AIR_CONDITIONED => 'Ber-AC',
            self::CHILLED => 'Dingin (chiller)',
            self::FROZEN => 'Beku (freezer)',
        };
    }
}
