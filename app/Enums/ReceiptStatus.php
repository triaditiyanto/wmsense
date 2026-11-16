<?php

namespace App\Enums;

/**
 * State machine dokumen receipt.
 *
 * DRAFT ──confirm──> OPEN ──(scan pertama)──> RECEIVING ──> RECEIVED ──> CLOSED
 *   └──cancel──> CANCELLED <──cancel── OPEN (selama belum ada barang diterima)
 *
 * RECEIVED ──> RECEIVING: dibuka ulang otomatis bila ada barang dibatalkan dari Intransit In
 * (barang belum disimpan ke rak), supaya qty-nya bisa diterima ulang.
 */
enum ReceiptStatus: string
{
    case DRAFT = 'DRAFT';
    case OPEN = 'OPEN';
    case RECEIVING = 'RECEIVING';
    case RECEIVED = 'RECEIVED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    /** @return list<self> status tujuan yang sah dari status ini */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::OPEN, self::CANCELLED],
            self::OPEN => [self::RECEIVING, self::CANCELLED],
            self::RECEIVING => [self::RECEIVED],
            self::RECEIVED => [self::CLOSED, self::RECEIVING],
            self::CLOSED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Header & detail hanya boleh diubah selama DRAFT. */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::RECEIVING => 'Receiving',
            self::RECEIVED => 'Received',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /** Warna badge Bootstrap. */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::OPEN => 'primary',
            self::RECEIVING => 'warning',
            self::RECEIVED => 'info',
            self::CLOSED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
