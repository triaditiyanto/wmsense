<?php

namespace App\Reports;

use App\Enums\RotationRule;
use App\Enums\ShipmentStatus;
use App\Models\Owner;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Outbound Summary: satu baris = satu shipment. Pesanan vs teralokasi vs dipick vs dikirim (fill rate),
 * baris kurang, ketepatan waktu kirim (tanggal rencana vs dispatch), lama proses (konfirmasi -> dikirim),
 * wave & Surat Jalan. Periode = tanggal rencana kirim.
 */
class OutboundSummaryReport extends Report
{
    public function key(): string
    {
        return 'out-summary';
    }

    public function title(): string
    {
        return 'Outbound Summary';
    }

    public function description(): string
    {
        return 'Ringkasan per shipment: pesanan vs dipick vs dikirim, fill rate, kurang, ketepatan waktu, lama proses.';
    }

    public function code(): string
    {
        return 'OUT_RPT_SUMMARY';
    }

    public function routeBase(): string
    {
        return 'outbound.report-summary';
    }

    public function section(): string
    {
        return 'Outbound';
    }

    public function periodLabel(): string
    {
        return 'Tanggal rencana kirim';
    }

    public function searchColumns(): array
    {
        return ['h.shipment_no', 'h.reference_no', 'h.ship_to_name', 'cu.name', 'wv.wave_no', 'do.do_no'];
    }

    public function searchPlaceholder(): string
    {
        return 'Cari shipment, referensi, customer, wave, DO...';
    }

    public function columns(): array
    {
        return [
            'shipment_no' => ['label' => 'No. Shipment', 'type' => 'string', 'sql' => 'h.shipment_no', 'default' => true, 'dim' => true],
            'reference_no' => ['label' => 'Referensi', 'type' => 'string', 'sql' => 'h.reference_no', 'default' => true],
            'status' => ['label' => 'Status', 'type' => 'string', 'sql' => 'h.status', 'default' => true, 'dim' => true, 'map' => fn ($v) => ShipmentStatus::tryFrom($v)?->label() ?? $v],
            'warehouse' => ['label' => 'Gudang', 'type' => 'string', 'sql' => 'w.code', 'dim' => true],
            'owner' => ['label' => 'Owner', 'type' => 'string', 'sql' => 'o.name', 'dim' => true],
            'customer' => ['label' => 'Customer', 'type' => 'string', 'sql' => 'cu.name', 'default' => true, 'dim' => true],
            'ship_to' => ['label' => 'Tujuan kirim', 'type' => 'string', 'sql' => 'h.ship_to_name', 'dim' => true],
            'priority' => ['label' => 'Prioritas', 'type' => 'number', 'sql' => 'h.priority', 'dim' => true],
            'rule' => ['label' => 'Aturan', 'type' => 'string', 'sql' => "COALESCE(wv.allocation_rule, h.allocation_rule, 'item')", 'dim' => true, 'map' => fn ($v) => RotationRule::tryFrom($v)?->value ?? 'Ikut item'],
            'order_date' => ['label' => 'Tgl order', 'type' => 'date', 'sql' => 'h.order_date', 'dim' => true],
            'expected_ship_date' => ['label' => 'Rencana kirim', 'type' => 'date', 'sql' => 'h.expected_ship_date', 'default' => true, 'dim' => true],
            'expected_month' => ['label' => 'Bulan rencana', 'type' => 'string', 'sql' => "DATE_FORMAT(h.expected_ship_date, '%Y-%m')", 'dim' => true],
            'wave_no' => ['label' => 'Wave', 'type' => 'string', 'sql' => 'wv.wave_no', 'default' => true, 'dim' => true],
            'do_no' => ['label' => 'Surat Jalan', 'type' => 'string', 'sql' => 'do.do_no', 'default' => true, 'dim' => true],
            'dispatched_at' => ['label' => 'Dikirim', 'type' => 'datetime', 'sql' => 'do.dispatched_at', 'default' => true],
            'punctuality' => ['label' => 'Ketepatan kirim', 'type' => 'string', 'default' => true, 'dim' => true,
                'sql' => "CASE WHEN h.status = 'CANCELLED' THEN 'Dibatalkan' WHEN do.dispatched_at IS NULL THEN (CASE WHEN h.expected_ship_date < CURDATE() THEN 'Terlambat (belum kirim)' ELSE 'Belum kirim' END)
                          WHEN DATE(do.dispatched_at) <= h.expected_ship_date THEN 'Tepat waktu' ELSE 'Terlambat' END"],
            'line_count' => ['label' => 'Jml item', 'type' => 'number', 'sql' => 'COALESCE(d.line_count, 0)', 'measure' => true],
            'qty_ordered' => ['label' => 'Qty pesan (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_ordered, 0)', 'default' => true, 'measure' => true],
            'qty_allocated' => ['label' => 'Qty alokasi (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_allocated, 0)', 'measure' => true],
            'qty_picked' => ['label' => 'Qty dipick (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_picked, 0)', 'default' => true, 'measure' => true],
            'qty_shipped' => ['label' => 'Qty dikirim (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_shipped, 0)', 'default' => true, 'measure' => true],
            'qty_short' => ['label' => 'Kurang (base)', 'type' => 'number', 'sql' => 'COALESCE(d.qty_ordered, 0) - COALESCE(d.qty_picked, 0)', 'measure' => true],
            'fill_rate' => ['label' => 'Fill rate %', 'type' => 'percent', 'measure' => true, 'agg' => 'avg', 'default' => true,
                'sql' => 'CASE WHEN COALESCE(d.qty_ordered, 0) > 0 THEN ROUND(d.qty_picked / d.qty_ordered * 100, 1) ELSE NULL END'],
            'short_lines' => ['label' => 'Baris kurang', 'type' => 'number', 'sql' => 'COALESCE(al.short_lines, 0)', 'measure' => true],
            'pick_lines' => ['label' => 'Baris pick', 'type' => 'number', 'sql' => 'COALESCE(al.pick_lines, 0)', 'measure' => true],
            'process_hours' => ['label' => 'Lama proses (jam)', 'type' => 'number', 'measure' => true, 'agg' => 'avg',
                'sql' => 'ROUND(TIMESTAMPDIFF(MINUTE, h.confirmed_at, do.dispatched_at) / 60, 2)'],
            'weight_kg' => ['label' => 'Berat dikirim (kg)', 'type' => 'number', 'sql' => 'ROUND(COALESCE(d.weight_kg, 0), 3)', 'measure' => true],
            'vehicle_no' => ['label' => 'Kendaraan', 'type' => 'string', 'sql' => 'do.vehicle_no', 'dim' => true],
            'doc_count' => ['label' => 'Jml shipment', 'type' => 'number', 'sql' => '1', 'measure' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse' => ['Gudang', Warehouse::orderBy('code')->pluck('code', 'id')->all()],
            'owner' => ['Owner', Owner::orderBy('code')->pluck('name', 'id')->all()],
            'status' => ['Status', collect(ShipmentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
        ];
    }

    protected function base(array $f): Builder
    {
        $details = DB::table('o_shipment_detail as sd')->join('master_item as it', 'it.id', '=', 'sd.item_id')
            ->groupBy('sd.shipment_header_id')->selectRaw('sd.shipment_header_id, COUNT(*) as line_count,
            SUM(sd.qty_ordered_base) as qty_ordered, SUM(sd.qty_allocated_base) as qty_allocated, SUM(sd.qty_picked_base) as qty_picked,
            SUM(sd.qty_shipped_base) as qty_shipped, SUM(sd.qty_shipped_base * COALESCE(it.weight_kg, 0)) as weight_kg');
        $allocs = DB::table('o_allocation')->where('status', '!=', 'CANCELLED')->groupBy('shipment_header_id')->selectRaw('shipment_header_id,
            COUNT(*) as pick_lines, SUM(CASE WHEN status = \'SHORT\' THEN 1 ELSE 0 END) as short_lines');

        return DB::table('o_shipment_header as h')
            ->join('master_warehouse as w', 'w.id', '=', 'h.warehouse_id')
            ->join('master_owner as o', 'o.id', '=', 'h.owner_id')
            ->join('master_customer as cu', 'cu.id', '=', 'h.customer_id')
            ->leftJoin('o_wave_header as wv', 'wv.id', '=', 'h.wave_id')
            ->leftJoin('o_delivery_order_header as do', 'do.id', '=', 'h.delivery_order_id')
            ->leftJoinSub($details, 'd', 'd.shipment_header_id', '=', 'h.id')
            ->leftJoinSub($allocs, 'al', 'al.shipment_header_id', '=', 'h.id')
            ->where(fn ($q) => $q->whereBetween('h.expected_ship_date', [$f['from'], $f['to']])
                ->orWhere(fn ($w) => $w->whereNull('h.expected_ship_date')->whereBetween(DB::raw('DATE(h.created_at)'), [$f['from'], $f['to']])))
            ->when($f['warehouse'] ?? null, fn ($q, $v) => $q->where('h.warehouse_id', $v))
            ->when($f['owner'] ?? null, fn ($q, $v) => $q->where('h.owner_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('h.status', $v))
            ->orderByDesc('h.expected_ship_date')->orderByDesc('h.id');
    }

    public function kpis(array $f): array
    {
        $rows = $this->query($f, ['status', 'qty_ordered', 'qty_picked', 'qty_shipped', 'punctuality', 'short_lines'])->get();
        $ordered = (float) $rows->sum('qty_ordered');
        $picked = (float) $rows->sum('qty_picked');
        $sent = $rows->whereIn('punctuality', ['Tepat waktu', 'Terlambat']);
        $onTime = $sent->count() ? round($sent->where('punctuality', 'Tepat waktu')->count() / $sent->count() * 100) : 0;

        return [
            ['Shipment', number_format($rows->count()), 'file-earmark-text'],
            ['Qty pesan', number_format($ordered, 0, ',', '.'), 'clipboard-data'],
            ['Qty dikirim', number_format((float) $rows->sum('qty_shipped'), 0, ',', '.'), 'truck'],
            ['Fill rate', ($ordered > 0 ? str_replace('.', ',', (string) round($picked / $ordered * 100, 1)) : 0).'%', 'percent'],
            ['Tepat waktu', $onTime.'%', 'alarm'],
            ['Belum kirim', number_format($rows->filter(fn ($r) => str_starts_with($r->punctuality, 'Belum') || str_starts_with($r->punctuality, 'Terlambat (belum'))->count()), 'hourglass-split'],
        ];
    }

    public function preset(): array
    {
        return ['rows' => 'customer', 'cols' => '', 'value' => 'fill_rate', 'agg' => 'avg', 'chart' => 'bar'];
    }
}
