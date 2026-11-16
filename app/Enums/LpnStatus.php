<?php

namespace App\Enums;

/**
 * EMPTY  : terdaftar, belum/tidak berisi stok (bisa dipakai)
 * ACTIVE : berisi stok
 * SHIPPED: sudah keluar gudang (outbound) - final
 * VOID   : dibatalkan (label rusak/hilang) - final
 *
 * EMPTY <-> ACTIVE (diisi / dikosongkan), ACTIVE -> SHIPPED, EMPTY -> VOID
 */
enum LpnStatus: string
{
    case EMPTY = 'EMPTY';
    case ACTIVE = 'ACTIVE';
    case SHIPPED = 'SHIPPED';
    case VOID = 'VOID';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::EMPTY => [self::ACTIVE, self::VOID],
            self::ACTIVE => [self::EMPTY, self::SHIPPED],
            self::SHIPPED, self::VOID => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Boleh dipakai untuk menampung stok (receiving / putaway / pindah). */
    public function isUsable(): bool
    {
        return $this === self::EMPTY || $this === self::ACTIVE;
    }

    public function label(): string
    {
        return match ($this) {
            self::EMPTY => 'Kosong',
            self::ACTIVE => 'Aktif',
            self::SHIPPED => 'Dikirim',
            self::VOID => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EMPTY => 'secondary',
            self::ACTIVE => 'primary',
            self::SHIPPED => 'success',
            self::VOID => 'danger',
        };
    }
}
