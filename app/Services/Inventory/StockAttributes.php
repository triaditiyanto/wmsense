<?php

namespace App\Services\Inventory;

use App\Enums\InventoryStatus;
use App\Models\Inventory;

/**
 * Identitas satu "tumpukan" stok: di lokasi mana, item apa, milik siapa, lot/exp berapa,
 * di LPN mana, statusnya apa. Satu kombinasi = satu baris o_inventory.
 * (readonly class = objek nilai yang tidak bisa diubah setelah dibuat)
 */
final readonly class StockAttributes
{
    public function __construct(
        public int $warehouseId,
        public int $locationId,
        public int $itemId,
        public int $ownerId,
        public ?string $lotNo = null,
        public ?string $expiryDate = null,  // Y-m-d
        public ?int $lpnId = null,
        public InventoryStatus $status = InventoryStatus::AVAILABLE,
    ) {
    }

    public function stockKey(): string
    {
        return Inventory::makeStockKey($this->itemId, $this->ownerId, $this->lotNo, $this->expiryDate, $this->lpnId, $this->status);
    }

    public static function fromInventory(Inventory $inv): self
    {
        return new self(
            $inv->warehouse_id, $inv->location_id, $inv->item_id, $inv->owner_id,
            $inv->lot_no, $inv->expiry_date?->format('Y-m-d'), $inv->lpn_id, $inv->status,
        );
    }
}
