<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Lpn;
use App\Models\ReceiptHeader;
use App\Support\Label\LabelCode;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data label cetak LPN & lokasi (dipakai Master > Cetak Label dan tombol cetak di Receipt).
 * Hasilnya dirender view `labels/print` (HTML + CSS @page, dicetak lewat dialog print browser:
 * printer label Zebra/TSC via driver, printer biasa di kertas stiker A4, atau "Simpan sebagai PDF").
 */
class LabelService
{
    public const MAX_LABELS = 500;

    /** Ukuran label: key => [nama, lebar mm, tinggi mm, untuk]. 'a4' = lembar stiker A4 3 x 8 (70 x 37 mm). */
    public const SIZES = [
        'pallet' => ['Pallet tag 100 x 150 mm', 100, 150, ['lpn']],
        'std' => ['Label 100 x 50 mm', 100, 50, ['lpn', 'location']],
        'beam' => ['Label rak 100 x 30 mm', 100, 30, ['location']],
        'small' => ['Label kecil 50 x 25 mm', 50, 25, ['lpn', 'location']],
        'a4' => ['Stiker A4 (24 label 70 x 37 mm)', 70, 37, ['lpn', 'location']],
    ];

    public const SYMBOLOGIES = [
        'qr' => 'QR Code',
        'barcode' => 'Barcode (Code 128)',
        'both' => 'QR + Barcode',
    ];

    /** Warna strip per level rak (1 = paling bawah). Membantu operator membaca level dari jauh. */
    public const LEVEL_COLORS = ['#e53935', '#1e88e5', '#43a047', '#fdd835', '#8e24aa', '#fb8c00', '#00acc1', '#6d4c41'];

    /**
     * @param  array{symbology: string, size: string, copies: int, contents?: bool, level_color?: bool}  $opt
     * @return array{labels: list<array>, opt: array, size: array}
     */
    public function lpnLabels(Collection $ids, array $opt, ?ReceiptHeader $receipt = null): array
    {
        $opt = $this->normalize($opt, 'lpn');
        $lpns = Lpn::with(['warehouse', 'location', 'pallet'])->whereIn('id', $ids)->orderBy('lpn_no')->get();
        $this->guardCount($lpns->count(), $opt['copies']);

        // Isi LPN (stok saat ini) untuk pallet tag
        $contents = $opt['contents']
            ? Inventory::query()->with(['item.baseUom'])
                ->whereIn('lpn_id', $lpns->pluck('id'))->where('qty_on_hand', '>', 0)
                ->orderBy('item_id')->get()->groupBy('lpn_id')
            : collect();

        $labels = $lpns->map(fn (Lpn $l) => [
            'code' => $l->lpn_no,
            'title' => $l->lpn_no,
            'kind' => 'LPN · '.$l->lpn_type->label(),
            'lines' => array_values(array_filter([
                $l->warehouse->code.($l->location ? ' · '.$l->location->code : ''),
                $l->pallet?->name,
                $receipt ? $receipt->receipt_no.($receipt->reference_no ? ' · '.$receipt->reference_no : '') : null,
            ])),
            'contents' => ($contents[$l->id] ?? collect())->map(fn (Inventory $i) => [
                'sku' => $i->item->sku,
                'name' => $i->item->name,
                'qty' => Qty::fmt($i->qty_on_hand).' '.$i->item->baseUom->code,
                'lot' => $i->lot_no,
                'exp' => $i->expiry_date?->format('d/m/Y'),
            ])->take(5)->values()->all(),
            'contents_more' => max(0, ($contents[$l->id] ?? collect())->count() - 5),
            'level' => null,
        ])->all();

        return $this->result($labels, $opt);
    }

    public function locationLabels(Collection $ids, array $opt): array
    {
        $opt = $this->normalize($opt, 'location');
        $locations = Location::with(['zone', 'warehouse', 'locationClass.locationType'])->whereIn('id', $ids)
            ->orderBy('pick_sequence')->orderBy('code')->get();
        $this->guardCount($locations->count(), $opt['copies']);

        $labels = $locations->map(function (Location $l) {
            $coord = array_filter([
                $l->aisle ? 'Aisle '.$l->aisle : null,
                $l->bay ? 'Bay '.$l->bay : null,
                $l->level ? 'Level '.$l->level : null,
                $l->position ? 'Pos '.$l->position : null,
            ]);

            return [
                'code' => $l->code,
                'title' => $l->code,
                'kind' => $l->locationFunction()->label(),
                'lines' => array_values(array_filter([
                    $l->warehouse->code.' · '.$l->zone->name,
                    $coord ? implode(' · ', $coord) : $l->locationClass->name,
                ])),
                'contents' => [],
                'contents_more' => 0,
                'level' => $l->level,
            ];
        })->all();

        return $this->result($labels, $opt);
    }

    /** Catat jejak cetak (print_count += salinan, last_printed_at). */
    public function markPrinted(string $type, Collection $ids, int $copies): void
    {
        $table = $type === 'lpn' ? 'master_lpn' : 'master_location';
        DB::table($table)->whereIn('id', $ids)->update([
            'print_count' => DB::raw('print_count + '.max(1, $copies)),
            'last_printed_at' => now(),
        ]);
    }

    /** Ukuran yang boleh dipakai untuk jenis label. */
    public static function sizesFor(string $type): array
    {
        return array_filter(self::SIZES, fn ($s) => in_array($type, $s[3], true));
    }

    private function normalize(array $opt, string $type): array
    {
        $sizes = self::sizesFor($type);
        $size = isset($sizes[$opt['size'] ?? '']) ? $opt['size'] : array_key_first($sizes);

        return [
            'type' => $type,
            'symbology' => isset(self::SYMBOLOGIES[$opt['symbology'] ?? '']) ? $opt['symbology'] : 'qr',
            'size' => $size,
            'copies' => max(1, min(10, (int) ($opt['copies'] ?? 1))),
            'contents' => (bool) ($opt['contents'] ?? $size === 'pallet'),
            'level_color' => (bool) ($opt['level_color'] ?? true),
        ];
    }

    private function guardCount(int $count, int $copies): void
    {
        if ($count === 0) {
            throw new BusinessException('Pilih minimal satu label untuk dicetak.');
        }
        if ($count * $copies > self::MAX_LABELS) {
            throw new BusinessException('Maksimal '.self::MAX_LABELS.' label sekali cetak (dipilih '.($count * $copies).'). Pecah menjadi beberapa kali cetak.');
        }
    }

    private function result(array $labels, array $opt): array
    {
        [, $w] = self::SIZES[$opt['size']];
        $warn = false;
        $withCode = array_map(function ($l) use ($opt, $w, &$warn) {
            $hasQr = in_array($opt['symbology'], ['qr', 'both'], true);
            $hasBar = in_array($opt['symbology'], ['barcode', 'both'], true) && ! ($opt['size'] === 'small' && $hasQr);   // 50x25: QR saja
            $l['level_color'] = $opt['level_color'] && $l['level'] ? self::LEVEL_COLORS[($l['level'] - 1) % count(self::LEVEL_COLORS)] : null;
            $l['qr'] = $hasQr ? LabelCode::qr($l['code']) : null;
            $l['bar'] = null;
            if ($hasBar) {
                // barcode selalu di baris sendiri selebar label: decoder kamera (zxing-js) gagal bila ada QR / blok warna sejajar
                $bar = LabelCode::code128($l['code'], $w - ($opt['size'] === 'pallet' ? 10 : ($opt['size'] === 'small' ? 3 : 5)));
                $l['bar'] = $bar['svg'];
                $warn = $warn || ! $bar['fits'];
            }

            return $l;
        }, $labels);

        // salinan berurutan: A, A, B, B (mudah dipotong per pallet)
        $out = [];
        foreach ($withCode as $l) {
            for ($i = 0; $i < $opt['copies']; $i++) {
                $out[] = $l;
            }
        }

        return ['labels' => $out, 'opt' => $opt, 'size' => self::SIZES[$opt['size']], 'barcode_warning' => $warn];
    }
}
