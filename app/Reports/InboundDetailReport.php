<?php

namespace App\Reports;

use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use App\Enums\ReceivingCondition;
use App\Models\Owner;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Inbound Detail: satu baris = satu baris terima (o_receipt_receiving): siapa, kapan, apa, berapa, di LPN & dock mana,
 * sudah disimpan ke mana. Periode = waktu terima.
 */
class InboundDetailReport extends Report
{
    public function key(): string
    {
        return 'detail';
    }

    public function title(): string
    {
        return 'Inbound Detail';
    }

    public function description(): string
    {
        return 'Setiap barang yang diterima: item, lot, qty, LPN, dock, sampai lokasi simpan.';
    }

    public function code(): string
    {
        return 'INB_RPT_DETAIL';
    }

    public function routeBase(): string
    {
        return 'inbound.report-detail';
    }

    public function periodLabel(): string
    {
        return 'Tanggal terima';
    }

    public function columns(): array
    {
        $type = fn ($v) => ReceiptType::tryFrom($v)?->label() ?? $v;
        $status = fn ($v) => ReceiptStatus::tryFrom($v)?->label() ?? $v;
        $cond = fn ($v) => ReceivingCondition::tryFrom($v)?->label() ?? $v;

        return [
            'received_at' => ['label' => 'Waktu terima', 'type' => 'datetime', 'sql' => 'r.received_at', 'default' => true],
            'received_date' => ['label' => 'Tanggal terima', 'type' => 'date', 'sql' => 'DATE(r.received_at)', 'dim' => true],
            'received_month' => ['label' => 'Bulan terima', 'type' => 'string', 'sql' => "DATE_FORMAT(r.received_at, '%Y-%m')", 'dim' => true],
            'receipt_no' => ['label' => 'No. Receipt', 'type' => 'string', 'sql' => 'h.receipt_no', 'default' => true, 'dim' => true],
            'reference_no' => ['label' => 'Referensi', 'type' => 'string', 'sql' => 'h.reference_no'],
            'receipt_type' => ['label' => 'Tipe', 'type' => 'string', 'sql' => 'h.receipt_type', 'dim' => true, 'map' => $type],
            'receipt_status' => ['label' => 'Status dokumen', 'type' => 'string', 'sql' => 'h.status', 'dim' => true, 'map' => $status],
            'warehouse' => ['label' => 'Gudang', 'type' => 'string', 'sql' => 'w.code', 'dim' => true],
            'owner' => ['label' => 'Owner', 'type' => 'string', 'sql' => 'o.name', 'dim' => true],
            'sender' => ['label' => 'Pengirim', 'type' => 'string', 'sql' => 'COALESCE(s.name, cu.name)', 'default' => true, 'dim' => true],
            'item_group' => ['label' => 'Item Group', 'type' => 'string', 'sql' => 'ig.name', 'dim' => true],
            'item_class' => ['label' => 'Item Class', 'type' => 'string', 'sql' => 'ic.name', 'dim' => true],
            'sku' => ['label' => 'SKU', 'type' => 'string', 'sql' => 'it.sku', 'default' => true, 'dim' => true],
            'item_name' => ['label' => 'Nama barang', 'type' => 'string', 'sql' => 'it.name', 'default' => true],
            'lot_no' => ['label' => 'Lot', 'type' => 'string', 'sql' => 'r.lot_no', 'default' => true, 'dim' => true],
            'expiry_date' => ['label' => 'Expired', 'type' => 'date', 'sql' => 'r.expiry_date', 'default' => true],
            'qty' => ['label' => 'Qty', 'type' => 'number', 'sql' => 'r.qty', 'default' => true],
            'uom' => ['label' => 'UOM', 'type' => 'string', 'sql' => 'u.code', 'default' => true, 'dim' => true],
            'qty_base' => ['label' => 'Qty (base)', 'type' => 'number', 'sql' => 'r.qty_base', 'default' => true, 'measure' => true],
            'base_uom' => ['label' => 'Base UOM', 'type' => 'string', 'sql' => 'bu.code'],
            'qty_damaged' => ['label' => 'Qty rusak (base)', 'type' => 'number', 'sql' => "CASE WHEN r.condition = 'DAMAGED' THEN r.qty_base ELSE 0 END", 'measure' => true],
            'weight_kg' => ['label' => 'Berat (kg)', 'type' => 'number', 'sql' => 'ROUND(r.qty_base * COALESCE(it.weight_kg, 0), 3)', 'measure' => true],
            'condition' => ['label' => 'Kondisi', 'type' => 'string', 'sql' => 'r.condition', 'default' => true, 'dim' => true, 'map' => $cond],
            'lpn_no' => ['label' => 'LPN', 'type' => 'string', 'sql' => 'p.lpn_no', 'default' => true, 'dim' => true],
            'dock' => ['label' => 'Dock', 'type' => 'string', 'sql' => 'dl.code', 'default' => true, 'dim' => true],
            'source' => ['label' => 'Sumber', 'type' => 'string', 'sql' => 'r.source', 'dim' => true],
            'received_by' => ['label' => 'Diterima oleh', 'type' => 'string', 'sql' => 'ru.name', 'dim' => true],
            'putaway_status' => ['label' => 'Status simpan', 'type' => 'string', 'default' => true, 'dim' => true,
                'sql' => "CASE WHEN r.cancelled_at IS NOT NULL THEN 'Dibatalkan' WHEN r.putaway_at IS NULL THEN 'Di dock' ELSE 'Tersimpan' END"],
            'putaway_location' => ['label' => 'Lokasi simpan', 'type' => 'string', 'sql' => 'tl.code', 'default' => true, 'dim' => true],
            'putaway_at' => ['label' => 'Waktu simpan', 'type' => 'datetime', 'sql' => 'r.putaway_at'],
            'dock_hours' => ['label' => 'Jam di dock', 'type' => 'number', 'measure' => true, 'agg' => 'avg',
                'sql' => 'ROUND(TIMESTAMPDIFF(MINUTE, r.received_at, COALESCE(r.putaway_at, r.cancelled_at, NOW())) / 60, 2)'],
            'cancel_reason' => ['label' => 'Alasan batal', 'type' => 'string', 'sql' => 'r.cancel_reason'],
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse' => ['Gudang', Warehouse::orderBy('code')->pluck('code', 'id')->all()],
            'owner' => ['Owner', Owner::orderBy('code')->pluck('name', 'id')->all()],
            'condition' => ['Kondisi', collect(ReceivingCondition::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()],
            'stored' => ['Status simpan', ['dock' => 'Di dock', 'stored' => 'Tersimpan', 'cancelled' => 'Dibatalkan', 'all' => 'Semua (termasuk batal)']],
        ];
    }

    protected function base(array $f): Builder
    {
        return DB::table('o_receipt_receiving as r')
            ->join('o_receipt_header as h', 'h.id', '=', 'r.receipt_header_id')
            ->join('master_warehouse as w', 'w.id', '=', 'h.warehouse_id')
            ->join('master_owner as o', 'o.id', '=', 'h.owner_id')
            ->leftJoin('master_supplier as s', 's.id', '=', 'h.supplier_id')
            ->leftJoin('master_customer as cu', 'cu.id', '=', 'h.customer_id')
            ->join('master_item as it', 'it.id', '=', 'r.item_id')
            ->join('master_item_class as ic', 'ic.id', '=', 'it.item_class_id')
            ->join('master_item_group as ig', 'ig.id', '=', 'ic.item_group_id')
            ->join('master_uom as u', 'u.id', '=', 'r.uom_id')
            ->join('master_uom as bu', 'bu.id', '=', 'it.base_uom_id')
            ->join('master_lpn as p', 'p.id', '=', 'r.lpn_id')
            ->join('master_location as dl', 'dl.id', '=', 'r.location_id')
            ->leftJoin('o_task_header as t', 't.id', '=', 'r.putaway_task_id')
            ->leftJoin('master_location as tl', 'tl.id', '=', 't.to_location_id')
            ->leftJoin('users as ru', 'ru.id', '=', 'r.received_by')
            ->whereBetween('r.received_at', [$f['from'].' 00:00:00', $f['to'].' 23:59:59'])
            ->when($f['warehouse'] ?? null, fn ($q, $v) => $q->where('h.warehouse_id', $v))
            ->when($f['owner'] ?? null, fn ($q, $v) => $q->where('h.owner_id', $v))
            ->when($f['condition'] ?? null, fn ($q, $v) => $q->where('r.condition', $v))
            ->when($f['stored'] ?? null, fn ($q, $v) => match ($v) {
                'dock' => $q->whereNull('r.cancelled_at')->whereNull('r.putaway_at'),
                'stored' => $q->whereNull('r.cancelled_at')->whereNotNull('r.putaway_at'),
                'cancelled' => $q->whereNotNull('r.cancelled_at'),
                default => $q,
            }, fn ($q) => $q->whereNull('r.cancelled_at'))   // default: tanpa baris batal
            ->orderByDesc('r.received_at')->orderByDesc('r.id');
    }

    public function kpis(array $f): array
    {
        $k = $this->base($f)->reorder()->selectRaw("COUNT(*) as n, COALESCE(SUM(r.qty_base), 0) as qty,
                COUNT(DISTINCT r.lpn_id) as lpn, COUNT(DISTINCT r.receipt_header_id) as docs,
                COALESCE(SUM(CASE WHEN r.condition = 'DAMAGED' THEN r.qty_base ELSE 0 END), 0) as damaged,
                SUM(CASE WHEN r.putaway_at IS NULL AND r.cancelled_at IS NULL THEN 1 ELSE 0 END) as at_dock")->first();
        $pct = $k->qty > 0 ? round($k->damaged / $k->qty * 100, 1) : 0;

        return [
            ['Baris diterima', number_format($k->n), 'list-ul'],
            ['Qty (base)', number_format((float) $k->qty, 0, ',', '.'), 'box-seam'],
            ['Pallet (LPN)', number_format($k->lpn), 'stack'],
            ['Dokumen', number_format($k->docs), 'file-earmark-text'],
            ['Rusak', str_replace('.', ',', (string) $pct).'%', 'exclamation-octagon'],
            ['Masih di dock', number_format($k->at_dock).' baris', 'truck'],
        ];
    }

    public function preset(): array
    {
        return ['rows' => 'received_date', 'cols' => 'condition', 'value' => 'qty_base', 'agg' => 'sum', 'chart' => 'bar'];
    }
}
