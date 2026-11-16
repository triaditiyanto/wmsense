<?php

namespace App\Enums;

/** Kondisi barang saat diterima -> menentukan status stok di o_inventory. */
enum ReceivingCondition: string
{
    case GOOD = 'GOOD';
    case DAMAGED = 'DAMAGED';

    public function inventoryStatus(): InventoryStatus
    {
        return match ($this) {
            self::GOOD => InventoryStatus::AVAILABLE,
            self::DAMAGED => InventoryStatus::DAMAGED,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Baik',
            self::DAMAGED => 'Rusak',
        };
    }

    public function color(): string
    {
        return $this === self::GOOD ? 'success' : 'danger';
    }
}
