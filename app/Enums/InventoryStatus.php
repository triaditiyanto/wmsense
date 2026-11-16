<?php

namespace App\Enums;

/**
 * Status kualitas stok. Bagian dari stock_key, jadi stok yang sama dengan status
 * berbeda berada di baris inventory yang berbeda. Perubahan status = movement
 * STATUS_OUT dari baris lama + STATUS_IN ke baris baru.
 */
enum InventoryStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case QC = 'QC';             // menunggu pemeriksaan
    case DAMAGED = 'DAMAGED';
    case EXPIRED = 'EXPIRED';
    case BLOCKED = 'BLOCKED';   // diblok manual (recall, investigasi)

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::QC => 'Menunggu QC',
            self::DAMAGED => 'Rusak',
            self::EXPIRED => 'Kedaluwarsa',
            self::BLOCKED => 'Diblok',
        };
    }

    public function isAllocatable(): bool
    {
        return $this === self::AVAILABLE;
    }
}
