<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\Warehouse;

/**
 * Penomoran otomatis per jenis dokumen + warehouse + periode.
 *   Dokumen : RCV-WH1-2611-0001 (periode YYMM)
 *   LPN     : LPN-WH1-26-000001 (periode YY)
 * Harus dipanggil DI DALAM DB::transaction milik pemanggil supaya nomor yang
 * sudah diambil ikut batal kalau transaksinya gagal.
 */
class DocumentNumberService
{
    /** Satu nomor dokumen: RCV-WH1-2611-0001 */
    public function next(string $docType, Warehouse $warehouse): string
    {
        $period = now()->format('ym');
        $no = $this->allocate($docType, $warehouse, $period, 1);

        return sprintf('%s-%s-%s-%04d', $docType, $warehouse->code, $period, $no);
    }

    /**
     * Ambil $count nomor berurutan sekaligus (satu kali lock).
     *
     * @return list<string>
     */
    public function nextBatch(string $prefix, Warehouse $warehouse, int $count, string $periodFormat = 'y', int $pad = 6): array
    {
        $period = now()->format($periodFormat);
        $first = $this->allocate($prefix, $warehouse, $period, $count);

        return array_map(
            fn (int $n) => sprintf('%s-%s-%s-%0'.$pad.'d', $prefix, $warehouse->code, $period, $n),
            range($first, $first + $count - 1),
        );
    }

    /** Naikkan counter sebanyak $count, kembalikan nomor PERTAMA yang didapat. */
    private function allocate(string $docType, Warehouse $warehouse, string $period, int $count): int
    {
        // Pastikan barisnya ada (aman dari race: unique index + insertOrIgnore)
        DocumentSequence::query()->insertOrIgnore([
            'doc_type' => $docType,
            'warehouse_id' => $warehouse->id,
            'period' => $period,
            'last_no' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kunci baris: transaksi lain yang minta nomor akan menunggu di sini
        $seq = DocumentSequence::query()
            ->where(['doc_type' => $docType, 'warehouse_id' => $warehouse->id, 'period' => $period])
            ->lockForUpdate()
            ->firstOrFail();

        $first = $seq->last_no + 1;
        $seq->increment('last_no', $count);

        return $first;
    }
}
