<?php

namespace App\Enums;

enum PalletMaterial: string
{
    case WOOD = 'WOOD';
    case PLASTIC = 'PLASTIC';
    case METAL = 'METAL';
    case PAPER = 'PAPER';

    public function label(): string
    {
        return match ($this) {
            self::WOOD => 'Kayu',
            self::PLASTIC => 'Plastik',
            self::METAL => 'Besi / Logam',
            self::PAPER => 'Kertas / Karton',
        };
    }
}
