<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemUom;
use App\Models\ReceiptDetail;
use App\Models\Uom;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Aturan item & konversi UOM:
 * - Setiap item selalu punya baris UOM dasar (base UOM) dengan faktor 1.
 * - Base UOM hanya boleh diganti selama item belum pernah dipakai transaksi.
 * - Faktor konversi harus bilangan bulat bila base UOM tidak boleh desimal (PCS).
 */
class ItemService
{
    public function create(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            $item = Item::create($data);
            $item->uoms()->create(['uom_id' => $item->base_uom_id, 'conversion_factor' => 1]);

            return $item;
        });
    }

    public function update(Item $item, array $data): Item
    {
        return DB::transaction(function () use ($item, $data) {
            $item = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $baseChanged = (int) $data['base_uom_id'] !== $item->base_uom_id;
            $ownerChanged = (int) $data['owner_id'] !== $item->owner_id;

            if (($baseChanged || $ownerChanged) && $this->isUsed($item)) {
                throw new BusinessException('Owner / base UOM tidak bisa diganti karena item sudah dipakai di transaksi.');
            }

            $item->update($data);

            if ($baseChanged) {
                // Konversi lama tidak berlaku lagi -> reset ke base UOM baru
                $item->uoms()->delete();
                $item->uoms()->create(['uom_id' => $item->base_uom_id, 'conversion_factor' => 1]);
            }

            return $item;
        });
    }

    public function addUom(Item $item, array $data): ItemUom
    {
        return DB::transaction(function () use ($item, $data) {
            if ($item->uoms()->where('uom_id', $data['uom_id'])->exists()) {
                throw new BusinessException('UOM ini sudah terdaftar untuk item tersebut.');
            }
            $values = $this->uomValues($item, $data);
            $this->resetDefaults($item, $values);

            return $item->uoms()->create($values);
        });
    }

    public function updateUom(ItemUom $itemUom, array $data): ItemUom
    {
        return DB::transaction(function () use ($itemUom, $data) {
            $item = $itemUom->item;
            $isBase = $itemUom->uom_id === $item->base_uom_id;

            if ((int) $data['uom_id'] !== $itemUom->uom_id) {
                if ($isBase) {
                    throw new BusinessException('UOM dasar tidak bisa diganti dari sini. Ubah base UOM di data item.');
                }
                if ($this->uomUsed($item->id, $itemUom->uom_id)) {
                    throw new BusinessException('UOM ini sudah dipakai di dokumen, tidak bisa diganti.');
                }
                if ($item->uoms()->where('uom_id', $data['uom_id'])->exists()) {
                    throw new BusinessException('UOM tujuan sudah terdaftar untuk item ini.');
                }
            }

            $values = $this->uomValues($item, $data);
            if ($isBase) {
                $values['conversion_factor'] = 1; // base selalu 1
            } elseif (Qty::cmp($values['conversion_factor'], $itemUom->conversion_factor) !== 0 && $this->uomUsed($item->id, $itemUom->uom_id)) {
                throw new BusinessException('Faktor konversi tidak bisa diubah karena UOM ini sudah dipakai di dokumen.');
            }
            $this->resetDefaults($item, $values, $itemUom->id);
            $itemUom->update($values);

            return $itemUom;
        });
    }

    public function deleteUom(ItemUom $itemUom): void
    {
        $item = $itemUom->item;
        if ($itemUom->uom_id === $item->base_uom_id) {
            throw new BusinessException('UOM dasar tidak bisa dihapus.');
        }
        if ($this->uomUsed($item->id, $itemUom->uom_id)) {
            throw new BusinessException('UOM ini sudah dipakai di dokumen, tidak bisa dihapus.');
        }
        $itemUom->delete();
    }

    public function isUsed(Item $item): bool
    {
        return ReceiptDetail::where('item_id', $item->id)->exists()
            || Inventory::where('item_id', $item->id)->exists();
    }

    private function uomUsed(int $itemId, int $uomId): bool
    {
        return ReceiptDetail::where('item_id', $itemId)->where('uom_id', $uomId)->exists();
    }

    private function uomValues(Item $item, array $data): array
    {
        $baseUom = Uom::findOrFail($item->base_uom_id);
        $factor = (string) $data['conversion_factor'];
        if (! $baseUom->allow_decimal && ! Qty::isWhole($factor)) {
            throw new BusinessException("Isi per UOM harus bilangan bulat karena base UOM {$baseUom->code} tidak boleh desimal.");
        }

        return [
            'uom_id' => (int) $data['uom_id'],
            'conversion_factor' => $factor,
            'barcode' => $data['barcode'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'length_cm' => $data['length_cm'] ?? null,
            'width_cm' => $data['width_cm'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
            'is_default_inbound' => (bool) ($data['is_default_inbound'] ?? false),
            'is_default_outbound' => (bool) ($data['is_default_outbound'] ?? false),
        ];
    }

    /** Hanya satu UOM default inbound & satu default outbound per item. */
    private function resetDefaults(Item $item, array $values, ?int $exceptId = null): void
    {
        foreach (['is_default_inbound', 'is_default_outbound'] as $flag) {
            if ($values[$flag]) {
                $item->uoms()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->update([$flag => false]);
            }
        }
    }
}
