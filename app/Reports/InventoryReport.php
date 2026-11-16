<?php

namespace App\Reports;

use App\Enums\InventoryStatus;
use App\Enums\LocationFunction;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Owner;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Inventory (posisi stok saat ini): satu baris = satu baris o_inventory (lokasi x LPN x item x lot x expired x status)
 * yang masih ada fisiknya. Snapshot -> tanpa filter periode. Klik baris = detail + kartu stok baris tsb.
 */
class InventoryReport extends Report
{
    public function key(): string
    {
        return 'inventory';
    }

    public function title(): string
    {
        return 'Inventory';
    }

    public function description(): string
    {
        return 'Posisi stok saat ini per lokasi, LPN, item, lot & expired.';
    }

    public function code(): string
    {
        return 'INV_STOCK';
    }

    public function routeBase(): string
    {
        return 'inventory.stock';
    }

    public function section(): string
    {
        return 'Inventory';
    }

    public function hasPeriod(): bool
    {
        return false;
    }

    public function periodLabel(): string
    {
        return 'Posisi stok';
    }

    public function searchColumns(): array
    {
        return ['it.sku', 'it.name', 'it.barcode', 'l.code', 'p.lpn_no', 'i.lot_no'];
    }

    public function searchPlaceholder(): string
    {
        return 'SKU, nama, lokasi, LPN, lot...';
    }

    public function rowKey(): ?string
    {
        return 'i.id';
    }

    public function columns(): array
    {
        $status = fn ($v) => InventoryStatus::tryFrom($v)?->label() ?? $v;
        $fn = fn ($v) => LocationFunction::tryFrom($v)?->label() ?? $v;
        $days = 'DATEDIFF(i.expiry_date, CURDATE())';
        $age = 'DATEDIFF(CURDATE(), DATE(i.received_at))';

        return [
            'warehouse' => ['label' => 'Gudang', 'type' => 'string', 'sql' => 'w.code', 'dim' => true],
            'zone' => ['label' => 'Zona', 'type' => 'string', 'sql' => 'z.name', 'dim' => true],
            'location' => ['label' => 'Lokasi', 'type' => 'string', 'sql' => 'l.code', 'default' => true, 'dim' => true],
            'location_function' => ['label' => 'Fungsi lokasi', 'type' => 'string', 'sql' => 'lt.location_function', 'dim' => true, 'map' => $fn],
            'aisle' => ['label' => 'Aisle', 'type' => 'string', 'sql' => 'l.aisle', 'dim' => true],
            'level' => ['label' => 'Level', 'type' => 'string', 'sql' => 'CAST(l.level AS CHAR)', 'dim' => true],
            'lpn_no' => ['label' => 'LPN', 'type' => 'string', 'sql' => 'p.lpn_no', 'default' => true, 'dim' => true],
            'owner' => ['label' => 'Owner', 'type' => 'string', 'sql' => 'o.name', 'default' => true, 'dim' => true],
            'item_group' => ['label' => 'Item Group', 'type' => 'string', 'sql' => 'ig.name', 'dim' => true],
            'item_class' => ['label' => 'Item Class', 'type' => 'string', 'sql' => 'ic.name', 'dim' => true],
            'sku' => ['label' => 'SKU', 'type' => 'string', 'sql' => 'it.sku', 'default' => true, 'dim' => true],
            'item_name' => ['label' => 'Nama barang', 'type' => 'string', 'sql' => 'it.name', 'default' => true],
            'lot_no' => ['label' => 'Lot', 'type' => 'string', 'sql' => 'i.lot_no', 'default' => true, 'dim' => true],
            'expiry_date' => ['label' => 'Expired', 'type' => 'date', 'sql' => 'i.expiry_date', 'default' => true],
            'days_to_expiry' => ['label' => 'Sisa umur (hari)', 'type' => 'number', 'sql' => $days, 'measure' => true, 'agg' => 'min'],
            'expiry_status' => ['label' => 'Status expired', 'type' => 'string', 'default' => true, 'dim' => true,
                'sql' => "CASE WHEN i.expiry_date IS NULL THEN 'Tanpa expired' WHEN {$days} < 0 THEN 'Kedaluwarsa'
                    WHEN {$days} <= 30 THEN '<= 30 hari' WHEN {$days} <= 90 THEN '31-90 hari' ELSE '> 90 hari' END"],
            'status' => ['label' => 'Status stok', 'type' => 'string', 'sql' => 'i.status', 'default' => true, 'dim' => true, 'map' => $status],
            'qty_on_hand' => ['label' => 'On hand', 'type' => 'number', 'sql' => 'i.qty_on_hand', 'default' => true, 'measure' => true],
            'qty_allocated' => ['label' => 'Dialokasikan', 'type' => 'number', 'sql' => 'i.qty_allocated', 'measure' => true],
            'qty_hold' => ['label' => 'Hold', 'type' => 'number', 'sql' => 'i.qty_hold', 'measure' => true],
            'qty_available' => ['label' => 'Tersedia', 'type' => 'number', 'sql' => 'i.qty_available', 'default' => true, 'measure' => true],
            'base_uom' => ['label' => 'UOM', 'type' => 'string', 'sql' => 'bu.code', 'default' => true, 'dim' => true],
            'received_at' => ['label' => 'Waktu masuk', 'type' => 'datetime', 'sql' => 'i.received_at'],
            'age_days' => ['label' => 'Umur stok (hari)', 'type' => 'number', 'sql' => $age, 'default' => true, 'measure' => true, 'agg' => 'avg'],
            'age_bucket' => ['label' => 'Kelompok umur', 'type' => 'string', 'dim' => true,
                'sql' => "CASE WHEN i.received_at IS NULL THEN '-' WHEN {$age} <= 7 THEN '0-7 hari' WHEN {$age} <= 30 THEN '8-30 hari'
                    WHEN {$age} <= 90 THEN '31-90 hari' ELSE '> 90 hari' END"],
            'weight_kg' => ['label' => 'Berat (kg)', 'type' => 'number', 'sql' => 'ROUND(i.qty_on_hand * COALESCE(it.weight_kg, 0), 3)', 'measure' => true],
            'volume_m3' => ['label' => 'Volume (m3)', 'type' => 'number', 'measure' => true,
                'sql' => 'ROUND(i.qty_on_hand * COALESCE(it.length_cm * it.width_cm * it.height_cm, 0) / 1000000, 4)'],
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse' => ['Gudang', Warehouse::orderBy('code')->pluck('code', 'id')->all()],
            'zone' => ['Zona', Zone::orderBy('warehouse_id')->orderBy('code')->pluck('name', 'id')->all()],
            'function' => ['Fungsi lokasi', collect(LocationFunction::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all()],
            'owner' => ['Owner', Owner::orderBy('code')->pluck('name', 'id')->all()],
            'status' => ['Status stok', collect(InventoryStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()],
            'expiry' => ['Expired', ['expired' => 'Sudah kedaluwarsa', '30' => '<= 30 hari lagi', '90' => '<= 90 hari lagi']],
        ];
    }

    protected function base(array $f): Builder
    {
        return DB::table('o_inventory as i')
            ->join('master_warehouse as w', 'w.id', '=', 'i.warehouse_id')
            ->join('master_location as l', 'l.id', '=', 'i.location_id')
            ->join('master_zone as z', 'z.id', '=', 'l.zone_id')
            ->join('master_location_class as lc', 'lc.id', '=', 'l.location_class_id')
            ->join('master_location_type as lt', 'lt.id', '=', 'lc.location_type_id')
            ->join('master_item as it', 'it.id', '=', 'i.item_id')
            ->join('master_item_class as ic', 'ic.id', '=', 'it.item_class_id')
            ->join('master_item_group as ig', 'ig.id', '=', 'ic.item_group_id')
            ->join('master_owner as o', 'o.id', '=', 'i.owner_id')
            ->join('master_uom as bu', 'bu.id', '=', 'it.base_uom_id')
            ->leftJoin('master_lpn as p', 'p.id', '=', 'i.lpn_id')
            ->where('i.qty_on_hand', '>', 0)
            ->when($f['warehouse'] ?? null, fn ($q, $v) => $q->where('i.warehouse_id', $v))
            ->when($f['zone'] ?? null, fn ($q, $v) => $q->where('l.zone_id', $v))
            ->when($f['function'] ?? null, fn ($q, $v) => $q->where('lt.location_function', $v))
            ->when($f['owner'] ?? null, fn ($q, $v) => $q->where('i.owner_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('i.status', $v))
            ->when($f['expiry'] ?? null, fn ($q, $v) => $v === 'expired'
                ? $q->where('i.expiry_date', '<', now()->toDateString())
                : $q->whereNotNull('i.expiry_date')->where('i.expiry_date', '<=', now()->addDays((int) $v)->toDateString()))
            ->orderByRaw('l.pick_sequence IS NULL, l.pick_sequence')->orderBy('l.code')->orderBy('it.sku')->orderBy('i.id');
    }

    public function kpis(array $f): array
    {
        $soon = now()->addDays(30)->toDateString();
        $k = $this->filtered($f)->reorder()->selectRaw("COUNT(*) as n, COUNT(DISTINCT i.item_id) as sku,
                COALESCE(SUM(i.qty_on_hand), 0) as qty, COALESCE(SUM(i.qty_available), 0) as avail,
                COUNT(DISTINCT i.lpn_id) as lpn, COUNT(DISTINCT i.location_id) as loc,
                SUM(CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= ? THEN 1 ELSE 0 END) as exp_soon", [$soon])->first();
        $n = fn ($v) => number_format((float) $v, 0, ',', '.');

        return [
            ['Baris stok', $n($k->n), 'list-ul'],
            ['SKU', $n($k->sku), 'upc'],
            ['On hand (base)', $n($k->qty), 'box-seam'],
            ['Tersedia (base)', $n($k->avail), 'check2-circle'],
            ['Lokasi · LPN', $n($k->loc).' · '.$n($k->lpn), 'grid-3x3-gap'],
            ['Expired / <= 30 hari', $n($k->exp_soon).' baris', 'hourglass-split'],
        ];
    }

    public function preset(): array
    {
        return ['rows' => 'sku', 'cols' => 'status', 'value' => 'qty_on_hand', 'agg' => 'sum', 'chart' => 'bar'];
    }

    /** Detail baris stok + kartu stok (30 movement terakhir) untuk modal. */
    public function rowDetail(int $id): string
    {
        $inv = Inventory::with(['item.baseUom', 'owner', 'location.zone', 'lpn', 'warehouse'])->findOrFail($id);
        $moves = InventoryMovement::with(['user', 'reference'])
            ->where('inventory_id', $inv->id)->latest('id')->limit(30)->get();

        return view('inventory._row', ['inv' => $inv, 'moves' => $moves])->render();
    }
}
