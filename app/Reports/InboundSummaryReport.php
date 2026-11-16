<?php

namespace App\Reports;

use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use App\Models\Owner;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Inbound Summary: satu baris = satu dokumen receipt. Rencana vs aktual (fill rate), selisih, rusak,
 * ketepatan waktu kedatangan, lama proses terima, progres putaway. Periode = tanggal rencana datang.
 */
class InboundSummaryReport extends Report
{
    public function key(): string
    {
        return 'summary';
    }

    public function title(): string
    {
        return 'Inbound Summary';
    }

    public function description(): string
    {
        return 'Ringkasan per dokumen: rencana vs diterima, fill rate, selisih, ketepatan waktu, progres simpan.';
    }

    public function code(): string
    {
        return 'INB_RPT_SUMMARY';
    }

    public function routeBase(): string
    {
        return 'inbound.report-summary';
    }

    public function periodLabel(): string
    {
        return 'Tanggal rencana datang';
    }

    public function columns(): array
    {
        return [
            'receipt_no' => ['label' => 'No. Receipt', 'type' => 'string', 'sql' => 'h.receipt_no', 'default' => true, 'dim' => true],
            'reference_no' => ['label' => 'Referensi', 'type' => 'string', 'sql' => 'h.reference_no', 'default' => true],
            'receipt_type' => ['label' => 'Tipe', 'type' => 'string', 'sql' => 'h.receipt_type', 'dim' => true, 'map' => fn ($v) => ReceiptType::tryFrom($v)?->label() ?? $v],
            'status' => ['label' => 'Status', 'type' => 'string', 'sql' => 'h.status', 'default' => true, 'dim' => true, 'map' => fn ($v) => ReceiptStatus::tryFrom($v)?->label() ?? $v],
            'warehouse' => ['label' => 'Gudang', 'type' => 'string', 'sql' => 'w.code', 'dim' => true],
            'owner' => ['label' => 'Owner', 'type' => 'string', 'sql' => 'o.name', 'dim' => true],
            'sender' => ['label' => 'Pengirim', 'type' => 'string', 'sql' => 'COALESCE(s.name, cu.name)', 'default' => true, 'dim' => true],
            'expected_date' => ['label' => 'Rencana datang', 'type' => 'date', 'sql' => 'h.expected_date', 'default' => true, 'dim' => true],
            'expected_month' => ['label' => 'Bulan rencana', 'type' => 'string', 'sql' => "DATE_FORMAT(h.expected_date, '%Y-%m')", 'dim' => true],
            'first_received' => ['label' => 'Mulai terima', 'type' => 'datetime', 'sql' => 'rv.first_received', 'default' => true],
            'last_received' => ['label' => 'Terakhir terima', 'type' => 'datetime', 'sql' => 'rv.last_received'],
            'arrival' => ['label' => 'Kedatangan', 'type' => 'string', 'default' => true, 'dim' => true,
                'sql' => "CASE WHEN rv.first_received IS NULL THEN 'Belum datang' WHEN DATE(rv.first_received) <= h.expected_date THEN 'Tepat waktu' ELSE 'Terlambat' END"],
            'line_count' => ['label' => 'Jml item', 'type' => 'number', 'sql' => 'COALESCE(d.line_count, 0)', 'measure' => true],
            'lpn_count' => ['label' => 'Jml LPN', 'type' => 'number', 'sql' => 'COALESCE(rv.lpn_count, 0)', 'default' => true, 'measure' => true],
            'qty_expected' => ['label' => 'Qty rencana (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_expected, 0)', 'default' => true, 'measure' => true],
            'qty_received' => ['label' => 'Qty diterima (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_received, 0)', 'default' => true, 'measure' => true],
            'qty_variance' => ['label' => 'Selisih (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_received, 0) - COALESCE(d.qty_expected, 0)', 'default' => true, 'measure' => true],
            'qty_damaged' => ['label' => 'Qty rusak (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_damaged, 0)', 'measure' => true],
            'fill_rate' => ['label' => 'Fill rate %', 'type' => 'percent', 'measure' => true, 'agg' => 'avg', 'default' => true,
                'sql' => 'CASE WHEN COALESCE(d.qty_expected, 0) > 0 THEN ROUND(LEAST(d.qty_received / d.qty_expected, 9.99) * 100, 1) ELSE NULL END'],
            'receive_hours' => ['label' => 'Lama terima (jam)', 'type' => 'number', 'measure' => true, 'agg' => 'avg',
                'sql' => 'ROUND(TIMESTAMPDIFF(MINUTE, rv.first_received, rv.last_received) / 60, 2)'],
            'putaway_pct' => ['label' => 'Tersimpan %', 'type' => 'percent', 'measure' => true, 'agg' => 'avg', 'default' => true,
                'sql' => 'CASE WHEN COALESCE(rv.line_count, 0) > 0 THEN ROUND(rv.stored_count / rv.line_count * 100, 1) ELSE NULL END'],
            'doc_count' => ['label' => 'Jml dokumen', 'type' => 'number', 'sql' => '1', 'measure' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse' => ['Gudang', Warehouse::orderBy('code')->pluck('code', 'id')->all()],
            'owner' => ['Owner', Owner::orderBy('code')->pluck('name', 'id')->all()],
            'status' => ['Status', collect(ReceiptStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
            'receipt_type' => ['Tipe', collect(ReceiptType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()],
        ];
    }

    protected function base(array $f): Builder
    {
        $details = DB::table('o_receipt_detail')->groupBy('receipt_header_id')->selectRaw('receipt_header_id,
            COUNT(*) as line_count, SUM(qty_expected_base) as qty_expected, SUM(qty_received_base) as qty_received,
            SUM(qty_damaged_base) as qty_damaged');
        $receivings = DB::table('o_receipt_receiving')->whereNull('cancelled_at')->groupBy('receipt_header_id')->selectRaw('receipt_header_id,
            MIN(received_at) as first_received, MAX(received_at) as last_received, COUNT(DISTINCT lpn_id) as lpn_count,
            COUNT(*) as line_count, SUM(CASE WHEN putaway_at IS NOT NULL THEN 1 ELSE 0 END) as stored_count');

        return DB::table('o_receipt_header as h')
            ->join('master_warehouse as w', 'w.id', '=', 'h.warehouse_id')
            ->join('master_owner as o', 'o.id', '=', 'h.owner_id')
            ->leftJoin('master_supplier as s', 's.id', '=', 'h.supplier_id')
            ->leftJoin('master_customer as cu', 'cu.id', '=', 'h.customer_id')
            ->leftJoinSub($details, 'd', 'd.receipt_header_id', '=', 'h.id')
            ->leftJoinSub($receivings, 'rv', 'rv.receipt_header_id', '=', 'h.id')
            ->whereBetween('h.expected_date', [$f['from'], $f['to']])
            ->when($f['warehouse'] ?? null, fn ($q, $v) => $q->where('h.warehouse_id', $v))
            ->when($f['owner'] ?? null, fn ($q, $v) => $q->where('h.owner_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('h.status', $v))
            ->when($f['receipt_type'] ?? null, fn ($q, $v) => $q->where('h.receipt_type', $v))
            ->orderByDesc('h.expected_date')->orderByDesc('h.id');
    }

    public function kpis(array $f): array
    {
        $rows = $this->query($f, ['status', 'qty_expected', 'qty_received', 'fill_rate', 'arrival', 'putaway_pct'])->get();
        $exp = (float) $rows->sum('qty_expected');
        $rec = (float) $rows->sum('qty_received');
        $arrived = $rows->where('arrival', '!=', 'Belum datang');
        $onTime = $arrived->count() ? round($arrived->where('arrival', 'Tepat waktu')->count() / $arrived->count() * 100) : 0;

        return [
            ['Dokumen', number_format($rows->count()), 'file-earmark-text'],
            ['Qty rencana', number_format($exp, 0, ',', '.'), 'clipboard-data'],
            ['Qty diterima', number_format($rec, 0, ',', '.'), 'box-seam'],
            ['Fill rate', ($exp > 0 ? str_replace('.', ',', (string) round($rec / $exp * 100, 1)) : 0).'%', 'percent'],
            ['Tepat waktu', $onTime.'%', 'alarm'],
            ['Belum selesai simpan', number_format($rows->filter(fn ($r) => $r->putaway_pct !== null && $r->putaway_pct < 100)->count()), 'hourglass-split'],
        ];
    }

    public function preset(): array
    {
        return ['rows' => 'sender', 'cols' => '', 'value' => 'fill_rate', 'agg' => 'avg', 'chart' => 'bar'];
    }
}
