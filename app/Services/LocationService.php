<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Location;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

class LocationService
{
    public const MAX_GENERATE = 2000;

    /**
     * Generate lokasi massal dengan pola kode {ZONA}-{AISLE}-{BAY}-{LEVEL}, mis. A-01-03-2.
     * Kode yang sudah ada dilewati (tidak error).
     *
     * @return array{created: int, skipped: int, first: ?string, last: ?string}
     */
    public function generate(array $data): array
    {
        $zone = Zone::findOrFail($data['zone_id']);
        $aisles = $this->expandAisles($data['aisles']);
        $bays = range((int) $data['bay_from'], (int) $data['bay_to']);
        $levels = range((int) $data['level_from'], (int) $data['level_to']);
        $total = count($aisles) * count($bays) * count($levels);

        if ($total > self::MAX_GENERATE) {
            throw new BusinessException("Akan dibuat {$total} lokasi, maksimal ".self::MAX_GENERATE.' per proses. Pecah menjadi beberapa kali generate.');
        }

        return DB::transaction(function () use ($zone, $aisles, $bays, $levels, $data) {
            $existing = Location::where('warehouse_id', $zone->warehouse_id)->pluck('code')->flip();
            $sequence = (int) Location::where('warehouse_id', $zone->warehouse_id)->max('pick_sequence');
            $created = 0;
            $skipped = 0;
            $codes = [];

            // Urutan: aisle -> bay -> level (urutan jalur picking ular sederhana)
            foreach ($aisles as $aisle) {
                foreach ($bays as $bay) {
                    foreach ($levels as $level) {
                        $code = sprintf('%s-%s-%02d-%d', $zone->code, $aisle, $bay, $level);
                        if ($existing->has($code)) {
                            $skipped++;
                            continue;
                        }
                        Location::create([
                            'warehouse_id' => $zone->warehouse_id,
                            'zone_id' => $zone->id,
                            'location_class_id' => $data['location_class_id'],
                            'code' => $code,
                            'status' => 'ACTIVE',
                            'aisle' => $aisle,
                            'bay' => sprintf('%02d', $bay),
                            'level' => $level,
                            'pick_sequence' => ++$sequence,
                        ]);
                        $codes[] = $code;
                        $created++;
                    }
                }
            }

            return ['created' => $created, 'skipped' => $skipped, 'first' => $codes[0] ?? null, 'last' => end($codes) ?: null];
        });
    }

    /** "01-03, 05" -> ['01','02','03','05'] ; "A,B" -> ['A','B'] */
    private function expandAisles(string $input): array
    {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', strtoupper($input)))) as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $width = max(strlen($m[1]), 2);
                foreach (range((int) $m[1], (int) $m[2]) as $n) {
                    $result[] = str_pad((string) $n, $width, '0', STR_PAD_LEFT);
                }
            } elseif (preg_match('/^[A-Z0-9]{1,5}$/', $part)) {
                $result[] = ctype_digit($part) ? str_pad($part, 2, '0', STR_PAD_LEFT) : $part;
            } else {
                throw new BusinessException("Format aisle \"{$part}\" tidak valid. Contoh: 01-05 atau A,B,C");
            }
        }
        if (! $result) {
            throw new BusinessException('Aisle wajib diisi.');
        }

        return array_values(array_unique($result));
    }
}
