<?php

namespace App\Services;

use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Inventory;
use App\Models\LocatingRule;
use App\Models\Location;
use App\Models\TaskHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Locating: cari lokasi simpan (fungsi STORAGE) untuk satu LPN.
 *
 * Saringan (lokasi yang tidak lolos tidak disarankan):
 *   1. aktif, satu gudang, fungsi STORAGE
 *   2. kelas suhu lokasi (atau zonanya) = kelas suhu item
 *   3. kapasitas cukup (LocationCapacityService) + slot yang sudah "dipesan" task putaway lain yang masih jalan
 *   4. aturan campur kelas lokasi: allow_mixed_items / allow_mixed_lots
 *   5. aturan locating (Master > Item Locating) mode "hanya area ini": lokasi di luar area aturan dibuang
 * Urutan:
 *   1. prioritas area aturan locating (target 1, 2, ...; di luar area = paling akhir) - LocatingRuleService::resolve
 *   2. konsolidasi: berisi item sama -> kosong -> masih muat (aturan bisa mematikan konsolidasi: kosong dulu)
 *   3. level: rendah dulu (default / aturan LOW) atau tinggi dulu (aturan HIGH)
 *   4. urutan pick, kode
 */
class LocatingService
{
    public function __construct(private LocationCapacityService $capacity, private LocatingRuleService $rules) {}

    /** Aturan locating yang berlaku untuk isi LPN (null = tanpa aturan, strategi dasar). */
    public function ruleFor(int $warehouseId, Collection $contents): ?LocatingRule
    {
        return $this->rules->forContents($warehouseId, $contents);
    }

    /**
     * @param  Collection<int, Inventory>  $contents  isi LPN (relasi item sudah di-load)
     * @param  LocatingRule|false|null  $rule  null = cari aturan otomatis, false = tanpa aturan, objek = pakai ini (simulasi)
     * @return list<array{id:int, code:string, zone:string, reason:string, cap:string, priority:?int}>
     */
    public function suggest(int $warehouseId, Collection $contents, int $limit = 5, ?int $ignoreTaskId = null, LocatingRule|false|null $rule = null): array
    {
        if ($contents->isEmpty()) {
            return [];
        }
        $rule = $rule === null ? $this->ruleFor($warehouseId, $contents) : ($rule ?: null);
        $consolidate = $rule?->prefer_consolidate ?? true;
        $levelDir = $rule?->level_preference === LocatingRule::LEVEL_HIGH ? -1 : 1;
        $itemIds = $contents->pluck('item_id')->unique()->values();
        $temps = $contents->map(fn ($i) => $i->item->temperature_class)->unique(fn ($t) => $t->value);
        $lots = $contents->pluck('lot_no', 'item_id');

        $candidates = Location::with(['zone', 'locationClass.locationType'])
            ->where('warehouse_id', $warehouseId)
            ->where('status', LocationStatus::ACTIVE)
            ->whereHas('locationClass.locationType', fn ($q) => $q->where('location_function', LocationFunction::STORAGE))
            ->get()
            ->filter(fn (Location $l) => $temps->count() === 1 && $l->effectiveTemperatureClass() === $temps->first());
        if ($candidates->isEmpty()) {
            return [];
        }

        $status = $this->capacity->statusFor($candidates, 1);
        $reserved = $this->reservations($candidates->pluck('id')->all(), $ignoreTaskId);
        $stock = $this->stockByLocation($candidates->pluck('id')->all());

        $rows = [];
        foreach ($candidates as $l) {
            $s = $status[$l->id];
            $res = $reserved[$l->id] ?? 0;
            if ($s['full'] || ($s['max_lpn'] !== null && $s['used_lpn'] + $res + 1 > $s['max_lpn'])) {
                continue;
            }

            $here = $stock[$l->id] ?? collect();
            $class = $l->locationClass;
            $otherItems = $here->pluck('item_id')->unique()->diff($itemIds);
            if (! $class->allow_mixed_items && ($otherItems->isNotEmpty() || ($here->isNotEmpty() && $itemIds->count() > 1))) {
                continue;
            }
            if (! $class->allow_mixed_lots && $here->contains(fn ($r) => $lots->has($r->item_id) && $r->lot_no !== $lots[$r->item_id])) {
                continue;
            }

            // aturan locating: prioritas area (null = di luar area aturan)
            $priority = $rule?->priorityFor($l);
            if ($rule && $rule->strict && $priority === null) {
                continue;
            }

            $sameItem = $here->contains(fn ($r) => $itemIds->contains($r->item_id));
            $isEmpty = $here->isEmpty() && $res === 0;
            [$rank, $reason] = match (true) {
                $sameItem => [$consolidate ? 0 : 1, 'Sudah berisi item yang sama (gabung)'],
                $isEmpty => [$consolidate ? 1 : 0, 'Lokasi kosong'],
                default => [2, 'Masih muat'],
            };
            if ($rule) {
                $reason = ($priority === null ? 'Di luar area aturan' : "Aturan prioritas {$priority}").' · '.$reason;
            }
            $rows[] = [
                'id' => $l->id,
                'code' => $l->code,
                'zone' => $l->zone->code,
                'reason' => $reason.($l->level ? " · level {$l->level}" : ''),
                'cap' => $s['max_lpn'] !== null ? ($s['used_lpn'] + $res).'/'.$s['max_lpn'].' LPN' : $s['used_lpn'].' LPN',
                'priority' => $priority,
                '_sort' => [$priority ?? 999, $rank, $levelDir * (int) $l->level, $l->pick_sequence ?? PHP_INT_MAX, $l->code],
            ];
        }

        usort($rows, fn ($a, $b) => $a['_sort'] <=> $b['_sort']);

        return array_map(fn ($r) => array_diff_key($r, ['_sort' => 1]), array_slice($rows, 0, $limit));
    }

    /**
     * Validasi lokasi tujuan pilihan operator (bisa berbeda dari saran).
     *
     * @return list<string> peringatan (kosong = aman). Kapasitas penuh / campur item = peringatan (butuh SPV).
     */
    public function check(Location $to, Collection $contents, ?int $ignoreTaskId = null): array
    {
        $warnings = [];
        $temps = $contents->map(fn ($i) => $i->item->temperature_class)->unique(fn ($t) => $t->value);
        if ($temps->count() === 1 && $to->effectiveTemperatureClass() !== $temps->first()) {
            $warnings[] = "Suhu lokasi {$to->code} ({$to->effectiveTemperatureClass()->value}) tidak sesuai item ({$temps->first()->value}).";
        }

        $s = $this->capacity->statusFor(collect([$to]), 1)[$to->id];
        $res = $this->reservations([$to->id], $ignoreTaskId)[$to->id] ?? 0;
        if ($s['full'] || ($s['max_lpn'] !== null && $s['used_lpn'] + $res + 1 > $s['max_lpn'])) {
            $warnings[] = "Lokasi {$to->code} penuh ({$s['used_lpn']}/{$s['max_lpn']} LPN".($res ? ", {$res} dipesan" : '').').';
        }

        $here = $this->stockByLocation([$to->id])[$to->id] ?? collect();
        $itemIds = $contents->pluck('item_id')->unique();
        if (! $to->locationClass->allow_mixed_items && $here->pluck('item_id')->unique()->diff($itemIds)->isNotEmpty()) {
            $warnings[] = "Lokasi {$to->code} tidak boleh campur item, sudah berisi item lain.";
        }

        // aturan locating mode "hanya area ini"
        $rule = $this->ruleFor($to->warehouse_id, $contents);
        if ($rule && $rule->strict && $rule->priorityFor($to) === null) {
            $warnings[] = "Lokasi {$to->code} di luar area aturan locating ".$this->rules->scopeName($rule->scope_type, $rule->scope_id).'.';
        }

        return $warnings;
    }

    /** Slot yang sedang dipesan task putaway lain (status IN_PROGRESS) per lokasi. */
    private function reservations(array $locationIds, ?int $ignoreTaskId): array
    {
        return TaskHeader::query()
            ->where('task_type', TaskType::PUTAWAY)->where('status', TaskStatus::IN_PROGRESS)
            ->whereIn('suggested_location_id', $locationIds)
            ->when($ignoreTaskId, fn ($q) => $q->whereKeyNot($ignoreTaskId))
            ->groupBy('suggested_location_id')
            ->selectRaw('suggested_location_id, COUNT(*) as n')
            ->pluck('n', 'suggested_location_id')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<int, Collection> stok per lokasi (item_id, lot_no) */
    private function stockByLocation(array $locationIds): array
    {
        return DB::table('o_inventory')
            ->whereIn('location_id', $locationIds)
            ->whereRaw('(qty_on_hand + qty_incoming) > 0')
            ->get(['location_id', 'item_id', 'lot_no'])
            ->groupBy('location_id')->all();
    }
}
