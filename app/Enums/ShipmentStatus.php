<?php

namespace App\Enums;

/**
 * State machine shipment (pesanan keluar).
 *
 * DRAFT ──confirm──> OPEN ──masuk wave──> WAVED ──run wave──> ALLOCATED ──mulai pick──> PICKING ──> PICKED ──DO dikirim──> DISPATCHED
 *   └──cancel──> CANCELLED <──cancel── OPEN
 * WAVED/ALLOCATED ──> OPEN : dilepas dari wave / wave dibatalkan (alokasi dilepas)
 * PICKED ──> OPEN : (tidak ada) ; shipment tanpa hasil pick saat wave selesai -> OPEN (bisa di-wave ulang)
 */
enum ShipmentStatus: string
{
    case DRAFT = 'DRAFT';
    case OPEN = 'OPEN';
    case WAVED = 'WAVED';
    case ALLOCATED = 'ALLOCATED';
    case PICKING = 'PICKING';
    case PICKED = 'PICKED';
    case DISPATCHED = 'DISPATCHED';
    case CANCELLED = 'CANCELLED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::OPEN, self::CANCELLED],
            self::OPEN => [self::WAVED, self::CANCELLED],
            self::WAVED => [self::ALLOCATED, self::OPEN],
            self::ALLOCATED => [self::PICKING, self::PICKED, self::OPEN],
            self::PICKING => [self::PICKED, self::OPEN],
            self::PICKED => [self::DISPATCHED],
            self::DISPATCHED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /** Sudah masuk wave (alokasi / picking berjalan). */
    public function inWave(): bool
    {
        return in_array($this, [self::WAVED, self::ALLOCATED, self::PICKING], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::WAVED => 'Dalam Wave',
            self::ALLOCATED => 'Teralokasi',
            self::PICKING => 'Picking',
            self::PICKED => 'Siap Kirim',
            self::DISPATCHED => 'Dikirim',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::OPEN => 'primary',
            self::WAVED => 'info',
            self::ALLOCATED => 'info',
            self::PICKING => 'warning',
            self::PICKED => 'success',
            self::DISPATCHED => 'dark',
            self::CANCELLED => 'danger',
        };
    }
}
