<?php

namespace App\Reports;

use App\Enums\AllocationStatus;
use App\Enums\RotationRule;
use App\Enums\ShipmentStatus;
use App\Models\Owner;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Outbound Detail: satu baris = satu baris alokasi / pick (o_allocation): shipment, customer, item, lot/expired,
 * dari lokasi & LPN mana, aturan alokasi, qty teralokasi vs dipick, short, staging, siapa & kapan dipick,
 * sampai DO / Surat Jalan. Periode = tanggal alokasi (created_at baris alokasi).
 */
class OutboundDetailReport extends Report
{
    public function key(): string
    {
        return 'out-detail';
    }

    public function title(): string
    {
        return 'Outbound Detail';
    }

    public function description(): string
    {
        return 'Setiap baris pick: shipment, customer, item, lot, dari lokasi/LPN mana, qty dipick vs alokasi, sampai Surat Jalan.';
    }

    public function code(): string
    {
        return 'OUT_RPT_DETAIL';
    }

    public function routeBase(): string
    {
        return 'outbound.report-detail';
    }

    public function section(): string
    {
        return 'Outbound';
    }

    public function periodLabel(): string
    {
        return 'Tanggal alokasi';
    }

    public function searchColumns(): array
    {
        return ['h.shipment_no', 'h.reference_no', 'h.ship_to_name', 'it.sku', 'it.name', 'a.lot_no', 'wv.wave_no', 'do.do_no'];
    }

    public function searchPlaceholder(): string
    {
        return 'Cari shipment, referensi, customer, SKU, lot, wave, DO...';
    }

    public function columns(): array
    {
        $status = fn ($v) => ShipmentStatus::tryFrom($v)?->label() ?? $v;
        $astatus = fn ($v) => AllocationStatus::tryFrom($v)?->label() ?? $v;
        $rule = fn ($v) => RotationRule::tryFrom($v)?->value ?? $v;

        return [
            'allocated_at' => ['label' => 'Waktu alokasi', 'type' => 'datetime', 'sql' => 'a.created_at', 'default' => true],
            'allocated_date' => ['label' => 'Tanggal alokasi', 'type' => 'date', 'sql' => 'DATE(a.created_at)', 'dim' => true],
            'allocated_month' => ['label' => 'Bulan alokasi', 'type' => 'string', 'sql' => "DATE_FORMAT(a.created_at, '%Y-%m')", 'dim' => true],
            'shipment_no' => ['label' => 'No. Shipment', 'type' => 'string', 'sql' => 'h.shipment_no', 'default' => true, 'dim' => true],
            'reference_no' => ['label' => 'Referensi', 'type' => 'string', 'sql' => 'h.reference_no'],
            'shipment_status' => ['label' => 'Status shipment', 'type' => 'string', 'sql' => 'h.status', 'dim' => true, 'map' => $status],
            'wave_no' => ['label' => 'Wave', 'type' => 'string', 'sql' => 'wv.wave_no', 'default' => true, 'dim' => true],
            'warehouse' => ['label' => 'Gudang', 'type' => 'string', 'sql' => 'w.code', 'dim' => true],
            'owner' => ['label' => 'Owner', 'type' => 'string', 'sql' => 'o.name', 'dim' => true],
            'customer' => ['label' => 'Customer', 'type' => 'string', 'sql' => 'cu.name', 'default' => true, 'dim' => true],
            'ship_to' => ['label' => 'Tujuan kirim', 'type' => 'string', 'sql' => 'h.ship_to_name', 'dim' => true],
            'item_group' => ['label' => 'Item Group', 'type' => 'string', 'sql' => 'ig.name', 'dim' => true],
            'item_class' => ['label' => 'Item Class', 'type' => 'string', 'sql' => 'ic.name', 'dim' => true],
            'sku' => ['label' => 'SKU', 'type' => 'string', 'sql' => 'it.sku', 'default' => true, 'dim' => true],
            'item_name' => ['label' => 'Nama barang', 'type' => 'string', 'sql' => 'it.name', 'default' => true],
            'lot_no' => ['label' => 'Lot', 'type' => 'string', 'sql' => 'a.lot_no', 'default' => true, 'dim' => true],
            'expiry_date' => ['label' => 'Expired', 'type' => 'date', 'sql' => 'a.expiry_date'],
            'location' => ['label' => 'Lokasi ambil', 'type' => 'string', 'sql' => 'l.code', 'default' => true, 'dim' => true],
            'zone' => ['label' => 'Zona', 'type' => 'string', 'sql' => 'z.code', 'dim' => true],
            'lpn_no' => ['label' => 'LPN', 'type' => 'string', 'sql' => 'p.lpn_no', 'dim' => true],
            'rule' => ['label' => 'Aturan', 'type' => 'string', 'sql' => 'a.rule', 'default' => true, 'dim' => true, 'map' => $rule],
            'qty_allocated' => ['label' => 'Qty alokasi (base)', 'type' => 'number', 'sql' => 'a.qty_allocated_base', 'default' => true, 'measure' => true],
            'qty_picked' => ['label' => 'Qty dipick (base)', 'type' => 'number', 'sql' => 'a.qty_picked_base', 'default' => true, 'measure' => true],
            'qty_short' => ['label' => 'Qty kurang (base)', 'type' => 'number', 'measure' => true,
                'sql' => "CASE WHEN a.status IN ('PICKED','SHORT') THEN a.qty_allocated_base - a.qty_picked_base ELSE 0 END"],
            'base_uom' => ['label' => 'Base UOM', 'type' => 'string', 'sql' => 'bu.code'],
            'weight_kg' => ['label' => 'Berat dipick (kg)', 'type' => 'number', 'sql' => 'ROUND(a.qty_picked_base * COALESCE(it.weight_kg, 0), 3)', 'measure' => true],
            'alloc_status' => ['label' => 'Status pick', 'type' => 'string', 'sql' => 'a.status', 'default' => true, 'dim' => true, 'map' => $astatus],
            'short_reason' => ['label' => 'Alasan kurang', 'type' => 'string', 'sql' => 'a.short_reason'],
            'staging' => ['label' => 'Staging', 'type' => 'string', 'sql' => 'sl.code', 'dim' => true],
            'picked_by' => ['label' => 'Dipick oleh', 'type' => 'string', 'sql' => 'pu.name', 'default' => true, 'dim' => true],
            'picked_at' => ['label' => 'Waktu pick', 'type' => 'datetime', 'sql' => 'a.picked_at', 'default' => true],
            'pick_source' => ['label' => 'Sumber pick', 'type' => 'string', 'sql' => 'a.pick_source', 'dim' => true],
            'pick_minutes' => ['label' => 'Menit alokasi→pick', 'type' => 'number', 'measure' => true, 'agg' => 'avg',
                'sql' => 'ROUND(TIMESTAMPDIFF(MINUTE, a.created_at, a.picked_at), 1)'],
            'do_no' => ['label' => 'Surat Jalan', 'type' => 'string', 'sql' => 'do.do_no', 'default' => true, 'dim' => true],
            'dispatched_at' => ['label' => 'Waktu kirim', 'type' => 'datetime', 'sql' => 'do.dispatched_at'],
            'vehicle_no' => ['label' => 'Kendaraan', 'type' => 'string', 'sql' => 'do.vehicle_no', 'dim' => true],
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse' => ['Gudang', Warehouse::orderBy('code')->pluck('code', 'id')->all()],
            'owner' => ['Owner', Owner::orderBy('code')->pluck('name', 'id')->all()],
            'alloc_status' => ['Status pick', collect(AllocationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
            'rule' => ['Aturan', collect(RotationRule::cases())->mapWithKeys(fn ($r) => [$r->value => $r->value.' - '.$r->short()])->all()],
        ];
    }

    protected function base(array $f): Builder
    {
        return DB::table('o_allocation as a')
            ->join('o_shipment_header as h', 'h.id', '=', 'a.shipment_header_id')
            ->join('o_wave_header as wv', 'wv.id', '=', 'a.wave_id')
            ->join('master_warehouse as w', 'w.id', '=', 'h.warehouse_id')
            ->join('master_owner as o', 'o.id', '=', 'h.owner_id')
            ->join('master_customer as cu', 'cu.id', '=', 'h.customer_id')
            ->join('master_item as it', 'it.id', '=', 'a.item_id')
            ->join('master_item_class as ic', 'ic.id', '=', 'it.item_class_id')
            ->join('master_item_group as ig', 'ig.id', '=', 'ic.item_group_id')
            ->join('master_uom as bu', 'bu.id', '=', 'it.base_uom_id')
            ->join('master_location as l', 'l.id', '=', 'a.location_id')
            ->join('master_zone as z', 'z.id', '=', 'l.zone_id')
            ->leftJoin('master_lpn as p', 'p.id', '=', 'a.lpn_id')
            ->leftJoin('master_location as sl', 'sl.id', '=', 'a.staging_location_id')
            ->leftJoin('users as pu', 'pu.id', '=', 'a.picked_by')
            ->leftJoin('o_delivery_order_header as do', 'do.id', '=', 'h.delivery_order_id')
            ->whereBetween('a.created_at', [$f['from'].' 00:00:00', $f['to'].' 23:59:59'])
            ->when($f['warehouse'] ?? null, fn ($q, $v) => $q->where('h.warehouse_id', $v))
            ->when($f['owner'] ?? null, fn ($q, $v) => $q->where('h.owner_id', $v))
            ->when($f['alloc_status'] ?? null, fn ($q, $v) => $q->where('a.status', $v), fn ($q) => $q->where('a.status', '!=', AllocationStatus::CANCELLED->value))
            ->when($f['rule'] ?? null, fn ($q, $v) => $q->where('a.rule', $v))
            ->orderByDesc('a.created_at')->orderByDesc('a.id');
    }

    public function kpis(array $f): array
    {
        $k = $this->base($f)->reorder()->selectRaw("COUNT(*) as n, COUNT(DISTINCT a.shipment_header_id) as docs,
                COALESCE(SUM(a.qty_allocated_base), 0) as alloc, COALESCE(SUM(a.qty_picked_base), 0) as picked,
                SUM(CASE WHEN a.status = 'SHORT' THEN 1 ELSE 0 END) as short_lines,
                SUM(CASE WHEN a.status = 'OPEN' THEN 1 ELSE 0 END) as open_lines,
                COALESCE(SUM(CASE WHEN do.status = 'DISPATCHED' THEN a.qty_picked_base ELSE 0 END), 0) as shipped")->first();
        $closed = $this->base($f)->reorder()->whereIn('a.status', ['PICKED', 'SHORT'])->selectRaw('COALESCE(SUM(a.qty_allocated_base), 0) as alloc, COALESCE(SUM(a.qty_picked_base), 0) as picked')->first();
        $rate = $closed->alloc > 0 ? round($closed->picked / $closed->alloc * 100, 1) : 0;

        return [
            ['Baris pick', number_format($k->n), 'list-ul'],
            ['Shipment', number_format($k->docs), 'file-earmark-text'],
            ['Qty dipick (base)', number_format((float) $k->picked, 0, ',', '.'), 'cart-check'],
            ['Pick rate', str_replace('.', ',', (string) $rate).'%', 'percent'],
            ['Baris kurang', number_format($k->short_lines), 'exclamation-triangle'],
            ['Belum dipick', number_format($k->open_lines).' baris', 'hourglass-split'],
        ];
    }

    public function preset(): array
    {
        return ['rows' => 'allocated_date', 'cols' => 'alloc_status', 'value' => 'qty_picked', 'agg' => 'sum', 'chart' => 'bar'];
    }
}
