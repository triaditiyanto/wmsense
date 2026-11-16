<?php

namespace App\Enums;

/**
 * Aturan rotasi stok = urutan pengambilan saat alokasi outbound.
 * Dipakai di master item (default per item), shipment (override), dan wave (override tertinggi).
 */
enum RotationRule: string
{
    case FIFO = 'FIFO'; // first in first out (received_at terlama dulu)
    case FEFO = 'FEFO'; // first expired first out (expiry_date terdekat dulu, tanpa expired paling akhir)
    case LIFO = 'LIFO'; // last in first out (received_at terbaru dulu, mis. drive-in per lane)
    case LEFO = 'LEFO'; // last expired first out (expiry_date terjauh dulu)

    public function label(): string
    {
        return match ($this) {
            self::FIFO => 'First In First Out',
            self::FEFO => 'First Expired First Out',
            self::LIFO => 'Last In First Out',
            self::LEFO => 'Last Expired First Out',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::FIFO => 'Masuk terlama dulu',
            self::FEFO => 'Expired terdekat dulu',
            self::LIFO => 'Masuk terbaru dulu',
            self::LEFO => 'Expired terjauh dulu',
        };
    }
}
