<?php

namespace App\Services;

use App\Enums\LocatingScope;
use App\Enums\LocatingTarget;
use App\Exceptions\BusinessException;
use App\Models\Item;
use App\Models\ItemClass;
use App\Models\ItemGroup;
use App\Models\Location;
use App\Models\LocatingRule;
use App\Models\LocatingRuleTarget;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan locating (Master > Item Locating).
 *   resolve()      : aturan yang berlaku untuk item di gudang: ITEM > ITEM_CLASS > ITEM_GROUP (paling spesifik menang)
 *   forContents()  : aturan untuk isi LPN (item dengan qty terbanyak yang menentukan)
 *   save()/delete(): simpan dari editor drag & drop
 *   draft()        : aturan belum disimpan (untuk simulasi di editor)
 */
class LocatingRuleService
{
    /** Cache per request: "wh|item" => ?LocatingRule */
    private array $cache = [];

    public function resolve(int $warehouseId, int $itemId): ?LocatingRule
    {
        $key = "{$warehouseId}|{$itemId}";
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $item = Item::with('itemClass')->find($itemId);
        if (! $item) {
            return $this->cache[$key] = null;
        }
        $scopes = [
            [LocatingScope::ITEM, $item->id],
            [LocatingScope::ITEM_CLASS, $item->item_class_id],
            [LocatingScope::ITEM_GROUP, $item->itemClass->item_group_id],
        ];
        $rules = LocatingRule::with(['targets.zone', 'targets.locationClass', 'targets.location'])
            ->where('warehouse_id', $warehouseId)->where('is_active', true)
            ->where(function ($q) use ($scopes) {
                foreach ($scopes as [$type, $id]) {
                    $q->orWhere(fn ($w) => $w->where('scope_type', $type)->where('scope_id', $id));
                }
            })->get();

        return $this->cache[$key] = $rules->sortByDesc(fn ($r) => $r->scope_type->specificity())->first();
    }

    /** @param  Collection  $contents  baris inventory isi LPN (item_id, qty_on_hand) */
    public function forContents(int $warehouseId, Collection $contents): ?LocatingRule
    {
        $main = $contents->sortByDesc(fn ($i) => (float) $i->qty_on_hand)->first();

        return $main ? $this->resolve($warehouseId, (int) $main->item_id) : null;
    }

    /** Nama cakupan, mis. "Item Group Makanan" / "Item SNK-001". */
    public function scopeName(LocatingScope $type, int $id): string
    {
        return $type->label().' '.match ($type) {
            LocatingScope::ITEM => Item::whereKey($id)->value('sku'),
            LocatingScope::ITEM_CLASS => ItemClass::whereKey($id)->value('name'),
            LocatingScope::ITEM_GROUP => ItemGroup::whereKey($id)->value('name'),
        };
    }

    /** Label area tujuan (relasi zone / locationClass / location sudah di-load). */
    public static function targetLabel(LocatingRuleTarget $t): string
    {
        $base = match ($t->target_type) {
            LocatingTarget::ZONE => 'Zona '.$t->zone?->name,
            LocatingTarget::AISLE => 'Aisle '.$t->aisle.' ('.$t->zone?->code.')',
            LocatingTarget::LOCATION_CLASS => 'Kelas '.$t->locationClass?->name,
            LocatingTarget::LOCATION => 'Lokasi '.$t->location?->code,
        };
        if ($t->level_min !== null || $t->level_max !== null) {
            $base .= ' · level '.($t->level_min ?? 1).($t->level_max !== $t->level_min ? '-'.($t->level_max ?? '…') : '');
        }

        return $base;
    }

    /**
     * Simpan aturan dari editor (ganti semua target).
     *
     * @param  array{warehouse_id:int, scope_type:string, scope_id:int, level_preference:?string, prefer_consolidate:bool,
     *               strict:bool, is_active:bool, notes:?string, targets:list<array>}  $data
     */
    public function save(array $data, User $by): LocatingRule
    {
        $targets = $this->normalizeTargets($data['warehouse_id'], $data['targets'] ?? []);
        if (! empty($data['strict']) && ! $targets) {
            throw new BusinessException('Mode "hanya area ini" butuh minimal satu area tujuan.');
        }

        return DB::transaction(function () use ($data, $targets, $by) {
            $rule = LocatingRule::updateOrCreate(
                ['warehouse_id' => $data['warehouse_id'], 'scope_type' => $data['scope_type'], 'scope_id' => $data['scope_id']],
                [
                    'level_preference' => $data['level_preference'] ?: null,
                    'prefer_consolidate' => (bool) ($data['prefer_consolidate'] ?? true),
                    'strict' => (bool) ($data['strict'] ?? false),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => $by->id,
                ],
            );
            $rule->targets()->delete();
            foreach ($targets as $i => $t) {
                $rule->targets()->create([...$t, 'seq' => $i + 1]);
            }
            $this->cache = [];

            return $rule->load('targets');
        });
    }

    public function delete(LocatingRule $rule): void
    {
        $rule->delete();   // target ikut terhapus (cascade)
        $this->cache = [];
    }

    /** Aturan belum disimpan (tidak masuk DB) untuk simulasi di editor. */
    public function draft(array $data): LocatingRule
    {
        $rule = new LocatingRule([
            'warehouse_id' => $data['warehouse_id'],
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'],
            'level_preference' => $data['level_preference'] ?: null,
            'prefer_consolidate' => (bool) ($data['prefer_consolidate'] ?? true),
            'strict' => (bool) ($data['strict'] ?? false),
            'is_active' => true,
        ]);
        $targets = collect($this->normalizeTargets($data['warehouse_id'], $data['targets'] ?? []))
            ->map(fn ($t, $i) => (new LocatingRuleTarget([...$t, 'seq' => $i + 1]))
                ->setRelation('zone', $t['zone_id'] ? Zone::find($t['zone_id']) : null)
                ->setRelation('locationClass', $t['location_class_id'] ? \App\Models\LocationClass::find($t['location_class_id']) : null)
                ->setRelation('location', $t['location_id'] ? Location::find($t['location_id']) : null));

        return $rule->setRelation('targets', $targets->values());
    }

    /** Validasi & rapikan target: area harus milik gudang yang sama, level_min <= level_max, tanpa duplikat. */
    private function normalizeTargets(int $warehouseId, array $targets): array
    {
        $zones = Zone::where('warehouse_id', $warehouseId)->pluck('id')->all();
        $out = [];
        $seen = [];
        foreach ($targets as $t) {
            $type = LocatingTarget::tryFrom($t['target_type'] ?? '') ?? throw new BusinessException('Jenis area tidak dikenal.');
            $row = ['target_type' => $type->value, 'zone_id' => null, 'aisle' => null, 'location_class_id' => null, 'location_id' => null,
                'level_min' => isset($t['level_min']) && $t['level_min'] !== '' ? max(1, (int) $t['level_min']) : null,
                'level_max' => isset($t['level_max']) && $t['level_max'] !== '' ? max(1, (int) $t['level_max']) : null];
            switch ($type) {
                case LocatingTarget::ZONE:
                case LocatingTarget::AISLE:
                    $row['zone_id'] = (int) ($t['zone_id'] ?? 0);
                    if (! in_array($row['zone_id'], $zones, true)) {
                        throw new BusinessException('Zona tidak ada di gudang ini.');
                    }
                    if ($type === LocatingTarget::AISLE) {
                        $row['aisle'] = strtoupper(trim((string) ($t['aisle'] ?? '')));
                        if (! Location::where('zone_id', $row['zone_id'])->where('aisle', $row['aisle'])->exists()) {
                            throw new BusinessException("Aisle {$row['aisle']} tidak ada di zona tersebut.");
                        }
                    }
                    break;
                case LocatingTarget::LOCATION_CLASS:
                    $row['location_class_id'] = (int) ($t['location_class_id'] ?? 0);
                    if (! \App\Models\LocationClass::whereKey($row['location_class_id'])->exists()) {
                        throw new BusinessException('Kelas lokasi tidak ditemukan.');
                    }
                    break;
                case LocatingTarget::LOCATION:
                    $row['location_id'] = (int) ($t['location_id'] ?? 0);
                    if (! Location::whereKey($row['location_id'])->where('warehouse_id', $warehouseId)->exists()) {
                        throw new BusinessException('Lokasi tidak ada di gudang ini.');
                    }
                    break;
            }
            if ($row['level_min'] !== null && $row['level_max'] !== null && $row['level_min'] > $row['level_max']) {
                [$row['level_min'], $row['level_max']] = [$row['level_max'], $row['level_min']];
            }
            $sig = implode('|', $row);
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $out[] = $row;
        }
        if (count($out) > 30) {
            throw new BusinessException('Maksimal 30 area per aturan.');
        }

        return $out;
    }
}
