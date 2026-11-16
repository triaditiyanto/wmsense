<?php

namespace App\Enums;

/** Jenis penyimpanan fisik pada location class. */
enum StorageType: string
{
    case SELECTIVE_RACK = 'selective_rack';
    case DRIVE_IN = 'drive_in';
    case FLOW_RACK = 'flow_rack';
    case SHELVING = 'shelving';
    case MEZZANINE = 'mezzanine';
    case FLOOR = 'floor';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SELECTIVE_RACK => 'Selective Rack',
            self::DRIVE_IN => 'Drive-in Rack',
            self::FLOW_RACK => 'Flow Rack',
            self::SHELVING => 'Shelving',
            self::MEZZANINE => 'Mezzanine',
            self::FLOOR => 'Lantai (floor stack)',
            self::OTHER => 'Lainnya',
        };
    }
}
