<?php

namespace App\Enums;

/** Jenis area tujuan di aturan locating. */
enum LocatingTarget: string
{
    case ZONE = 'ZONE';
    case AISLE = 'AISLE';
    case LOCATION_CLASS = 'LOCATION_CLASS';
    case LOCATION = 'LOCATION';

    public function label(): string
    {
        return match ($this) {
            self::ZONE => 'Zona',
            self::AISLE => 'Aisle',
            self::LOCATION_CLASS => 'Kelas lokasi',
            self::LOCATION => 'Lokasi',
        };
    }
}
