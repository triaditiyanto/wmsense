<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hitung pemakaian & sisa kapasitas lokasi.
 * Pemakaian = stok fisik + stok yang akan masuk (qty_incoming, reservasi putaway):
 *   - LPN    : jumlah LPN berbeda yang punya stok di lokasi
 *   - berat  : qty (base UOM) x berat item per base UOM
 *   - volume : qty (base UOM) x (P x L x T item) / 1.000.000
 * Dipakai: saran dock saat receiving, locating (putaway), replenishment.
 */
class LocationCapacityService
{
    /**
     * Status kapasitas banyak lokasi sekaligus (1 query agregat).
     * $addLpn = jumlah LPN yang ingin ditaruh -> menentukan 'full'.
     *
     * @param  Collection<int, Location>  $locations  (locationClass sudah di-load)
     * @return array<int, array> key = location_id
     */
    public function statusFor(Collection $locations, int $addLpn = 1): array
    {
        $usage = $this->usage($locations->pluck('id')->all());

        return $locations->mapWithKeys(fn (Location $l) => [$l->id => $this->evaluate($l, $usage[$l->id] ?? null, $addLpn)])->all();
    }

    /** @return array<int, array{lpn: int, weight: float, volume: float}> */
    public function usage(array $locationIds): array
    {
        if (! $locationIds) {
            return [];
        }

        return DB::table('o_inventory as i')
            ->join('master_item as it', 'it.id', '=', 'i.item_id')
            ->whereIn('i.location_id', $locationIds)
            ->whereRaw('(i.qty_on_hand + i.qty_incoming) > 0')
            ->groupBy('i.location_id')
            ->selectRaw('i.location_id')
            ->selectRaw('COUNT(DISTINCT i.lpn_id) as lpn')
            ->selectRaw('SUM((i.qty_on_hand + i.qty_incoming) * COALESCE(it.weight_kg, 0)) as weight')
            ->selectRaw('SUM((i.qty_on_hand + i.qty_incoming) * COALESCE(it.length_cm * it.width_cm * it.height_cm, 0)) / 1000000 as volume')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->location_id => ['lpn' => (int) $r->lpn, 'weight' => (float) $r->weight, 'volume' => (float) $r->volume]])
            ->all();
    }

    private function evaluate(Location $l, ?array $u, int $addLpn): array
    {
        $cap = $l->capacity();
        $u ??= ['lpn' => 0, 'weight' => 0.0, 'volume' => 0.0];

        // persentase terisi = rasio tertinggi dari kapasitas yang ditentukan
        $ratios = [];
        if ($cap['max_lpn'] !== null) {
            $ratios[] = $cap['max_lpn'] > 0 ? $u['lpn'] / $cap['max_lpn'] : 1;
        }
        if ($cap['max_weight_kg']) {
            $ratios[] = $u['weight'] / $cap['max_weight_kg'];
        }
        if ($cap['max_volume_m3']) {
            $ratios[] = $u['volume'] / $cap['max_volume_m3'];
        }

        $full = ($cap['max_lpn'] !== null && $u['lpn'] + $addLpn > $cap['max_lpn'])
            || ($cap['max_weight_kg'] && $u['weight'] >= $cap['max_weight_kg'])
            || ($cap['max_volume_m3'] && $u['volume'] >= $cap['max_volume_m3']);

        $usedText = $cap['max_lpn'] !== null ? "{$u['lpn']}/{$cap['max_lpn']} LPN" : "{$u['lpn']} LPN";

        return [
            'id' => $l->id,
            'code' => $l->code,
            'used_lpn' => $u['lpn'],
            'max_lpn' => $cap['max_lpn'],
            'weight' => round($u['weight'], 3),
            'max_weight_kg' => $cap['max_weight_kg'],
            'volume' => round($u['volume'], 4),
            'max_volume_m3' => $cap['max_volume_m3'],
            'percent' => $ratios ? (int) min(100, round(max($ratios) * 100)) : null,
            'empty' => $u['lpn'] === 0 && $u['weight'] == 0.0,
            'full' => $full,
            'label' => "{$l->code} · {$usedText}".($full ? ' · PENUH' : ''),
        ];
    }
}
