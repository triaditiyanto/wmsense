<?php

namespace App\Enums;

enum ReceiptType: string
{
    case PURCHASE = 'PURCHASE';   // pembelian dari supplier
    case RETURN = 'RETURN';       // retur dari customer
    case TRANSFER = 'TRANSFER';   // kiriman dari gudang lain
    case OTHER = 'OTHER';

    /** Retur customer: pengirimnya customer, selain itu supplier. */
    public function usesCustomer(): bool
    {
        return $this === self::RETURN;
    }

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Pembelian (PO)',
            self::RETURN => 'Retur Customer',
            self::TRANSFER => 'Transfer Antar Gudang',
            self::OTHER => 'Lainnya',
        };
    }
}
