<?php

namespace App\Enums;

/** DO / Surat Jalan: DRAFT (disusun) -> DISPATCHED (stok keluar gudang) | CANCELLED. */
enum DeliveryOrderStatus: string
{
    case DRAFT = 'DRAFT';
    case DISPATCHED = 'DISPATCHED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::DISPATCHED => 'Dikirim',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::DISPATCHED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
