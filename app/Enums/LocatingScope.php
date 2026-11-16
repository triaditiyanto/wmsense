<?php

namespace App\Enums;

/** Cakupan aturan locating. Resolusi: aturan paling spesifik menang (ITEM > ITEM_CLASS > ITEM_GROUP). */
enum LocatingScope: string
{
    case ITEM_GROUP = 'ITEM_GROUP';
    case ITEM_CLASS = 'ITEM_CLASS';
    case ITEM = 'ITEM';

    public function label(): string
    {
        return match ($this) {
            self::ITEM_GROUP => 'Item Group',
            self::ITEM_CLASS => 'Item Class',
            self::ITEM => 'Item',
        };
    }

    /** Makin besar makin spesifik. */
    public function specificity(): int
    {
        return match ($this) {
            self::ITEM_GROUP => 1,
            self::ITEM_CLASS => 2,
            self::ITEM => 3,
        };
    }
}
