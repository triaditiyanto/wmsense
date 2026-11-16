<?php

namespace App\Enums;

/**
 * Fungsi lokasi bagi sistem. Disimpan di master_location_type.location_function;
 * logika (allocatable, receiving, dll.) membaca enum ini, bukan kode tipe buatan user.
 */
enum LocationFunction: string
{
    case RECEIVING = 'RECEIVING';   // dock / staging inbound
    case STORAGE = 'STORAGE';       // rak / lantai penyimpanan (reserve)
    case PICK_FACE = 'PICK_FACE';   // lokasi picking yang di-replenish
    case STAGING_OUT = 'STAGING_OUT'; // hasil picking menunggu packing
    case SHIPPING = 'SHIPPING';     // dock outbound
    case QC = 'QC';
    case QUARANTINE = 'QUARANTINE';
    case VIRTUAL = 'VIRTUAL';       // mis. lokasi selisih / in-transit

    public function label(): string
    {
        return match ($this) {
            self::RECEIVING => 'Receiving / Dock Inbound',
            self::STORAGE => 'Storage',
            self::PICK_FACE => 'Pick Face',
            self::STAGING_OUT => 'Staging Outbound',
            self::SHIPPING => 'Shipping Dock',
            self::QC => 'Quality Control',
            self::QUARANTINE => 'Karantina',
            self::VIRTUAL => 'Virtual',
        };
    }

    /** Apakah stok di tipe lokasi ini boleh dialokasikan untuk order. */
    public function isAllocatable(): bool
    {
        return in_array($this, [self::STORAGE, self::PICK_FACE], true);
    }
}
