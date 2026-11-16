<?php

namespace App\Enums;

enum DiscrepancyType: string
{
    case SHORT = 'SHORT';     // diterima kurang dari ASN
    case OVER = 'OVER';       // diterima lebih dari ASN
    case DAMAGED = 'DAMAGED'; // ada barang rusak

    public function label(): string
    {
        return match ($this) {
            self::SHORT => 'Kurang',
            self::OVER => 'Lebih',
            self::DAMAGED => 'Rusak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SHORT => 'warning',
            self::OVER => 'info',
            self::DAMAGED => 'danger',
        };
    }
}
