<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Warehouse;
use App\Services\LocationCapacityService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Inventory > Peta Gudang 3D: denah & rak satu gudang dalam 3D (Three.js lokal, public/js/warehouse-3d.js).
 *   index    : halaman
 *   data     : semua lokasi gudang + koordinat (aisle/bay/level/position), dimensi kelas, isi & kapasitas (JSON)
 *   location : isi satu lokasi (LPN, item, qty, lot, expired) untuk panel samping
 * Tata letak dihitung di browser dari koordinat lokasi: aisle = baris rak, bay = kolom, level = tingkat;
 * lokasi tanpa aisle (dock, lantai) dikelompokkan per fungsi sebagai area lantai.
 */
class WarehouseMapController extends Controller
{
    public function index(Request $request): View
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('code')->get();

        return view('inventory.map', [
            'warehouses' => $warehouses,
            'warehouse' => $warehouses->firstWhere('id', $request->integer('warehouse')) ?? $warehouses->first(),
        ]);
    }

    public function data(Request $request, LocationCapacityService $capacity): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($request->integer('warehouse'));
        $locations = Location::with(['zone', 'locationClass.locationType'])
            ->where('warehouse_id', $warehouse->id)
            ->orderByRaw('pick_sequence IS NULL, pick_sequence')->orderBy('code')->get();
        $cap = $capacity->statusFor($locations, 0);

        // isi per lokasi (1 query agregat)
        $stock = DB::table('o_inventory as i')
            ->join('master_item as it', 'it.id', '=', 'i.item_id')
            ->join('master_owner as o', 'o.id', '=', 'i.owner_id')
            ->where('i.warehouse_id', $warehouse->id)->where('i.qty_on_hand', '>', 0)
            ->groupBy('i.location_id')
            ->selectRaw('i.location_id, SUM(i.qty_on_hand) as qty, SUM(i.qty_available) as avail, COUNT(DISTINCT i.item_id) as skus,
                MIN(i.expiry_date) as min_exp, MIN(i.received_at) as oldest,
                SUM(CASE WHEN i.status <> \'AVAILABLE\' OR i.qty_hold > 0 THEN 1 ELSE 0 END) as blocked,
                GROUP_CONCAT(DISTINCT it.sku ORDER BY it.sku SEPARATOR \'|\') as sku_list,
                GROUP_CONCAT(DISTINCT it.name ORDER BY it.sku SEPARATOR \'|\') as name_list,
                GROUP_CONCAT(DISTINCT o.code ORDER BY o.code SEPARATOR \'|\') as owner_list,
                GROUP_CONCAT(DISTINCT i.lot_no SEPARATOR \'|\') as lot_list')
            ->get()->keyBy('location_id');
        $lpns = DB::table('master_lpn')->where('warehouse_id', $warehouse->id)->where('status', 'ACTIVE')
            ->whereNotNull('location_id')->groupBy('location_id')
            ->selectRaw('location_id, GROUP_CONCAT(lpn_no ORDER BY lpn_no SEPARATOR \'|\') as list')->pluck('list', 'location_id');
        // slot yang sedang dipesan putaway (task berjalan)
        $reserved = DB::table('o_task_header')->where('warehouse_id', $warehouse->id)->where('status', TaskStatus::IN_PROGRESS->value)
            ->whereNotNull('suggested_location_id')->groupBy('suggested_location_id')
            ->selectRaw('suggested_location_id as id, COUNT(*) as n')->pluck('n', 'id');

        $today = now()->startOfDay();
        $split = fn (?string $s) => $s === null || $s === '' ? [] : explode('|', $s);

        $rows = $locations->map(function (Location $l) use ($cap, $stock, $lpns, $reserved, $today, $split) {
            $s = $stock[$l->id] ?? null;
            $c = $cap[$l->id];
            $class = $l->locationClass;

            return [
                'id' => $l->id,
                'code' => $l->code,
                'zone' => $l->zone->code,
                'zone_name' => $l->zone->name,
                'fn' => $l->locationFunction()->value,
                'fn_label' => $l->locationFunction()->label(),
                'storage' => $class->storage_type,
                'class' => $class->name,
                'aisle' => $l->aisle,
                'bay' => $l->bay,
                'level' => $l->level,
                'position' => $l->position,
                'seq' => $l->pick_sequence,
                'status' => $l->status->value,
                // dimensi slot (cm) dari kelas lokasi; null = pakai default di JS
                'dim' => [$class->length_cm ? (float) $class->length_cm : null, $class->width_cm ? (float) $class->width_cm : null, $class->height_cm ? (float) $class->height_cm : null],
                'max_lpn' => $c['max_lpn'],
                'used_lpn' => $c['used_lpn'],
                'percent' => $c['percent'],
                'full' => $c['max_lpn'] !== null && $c['used_lpn'] >= $c['max_lpn'],
                'reserved' => (int) ($reserved[$l->id] ?? 0),
                'qty' => $s ? (float) $s->qty : 0,
                'skus' => $s ? $split($s->sku_list) : [],
                'names' => $s ? $split($s->name_list) : [],
                'owners' => $s ? $split($s->owner_list) : [],
                'lots' => $s ? $split($s->lot_list) : [],
                'lpns' => $split($lpns[$l->id] ?? null),
                'exp_days' => $s && $s->min_exp ? (int) $today->diffInDays($s->min_exp, false) : null,
                'age_days' => $s && $s->oldest ? (int) \Carbon\Carbon::parse($s->oldest)->startOfDay()->diffInDays($today) : null,
                'blocked' => $s ? (int) $s->blocked : 0,
            ];
        });

        return response()->json([
            'warehouse' => ['id' => $warehouse->id, 'code' => $warehouse->code, 'name' => $warehouse->name],
            'locations' => $rows->values(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    public function location(Location $location): JsonResponse
    {
        $location->load(['zone', 'locationClass.locationType']);
        $rows = Inventory::with(['item.baseUom', 'owner', 'lpn'])
            ->where('location_id', $location->id)->where('qty_on_hand', '>', 0)
            ->orderBy('lpn_id')->orderBy('item_id')->get();
        $cap = app(LocationCapacityService::class)->statusFor(collect([$location]), 0)[$location->id];

        return response()->json([
            'id' => $location->id,
            'code' => $location->code,
            'title' => $location->locationFunction()->label().' · '.$location->zone->name,
            'class' => $location->locationClass->name,
            'coord' => collect(['Aisle' => $location->aisle, 'Bay' => $location->bay, 'Level' => $location->level, 'Pos' => $location->position])
                ->filter()->map(fn ($v, $k) => "{$k} {$v}")->values()->join(' · '),
            'status' => $location->status->label(),
            'capacity' => $cap,
            'stock_url' => route('inventory.stock.index', ['q' => $location->code]),
            'rows' => $rows->map(fn (Inventory $i) => [
                'lpn' => $i->lpn?->lpn_no,
                'sku' => $i->item->sku,
                'name' => $i->item->name,
                'owner' => $i->owner->code,
                'lot' => $i->lot_no,
                'exp' => $i->expiry_date?->format('d/m/Y'),
                'status' => $i->status->label(),
                'available' => $i->status->isAllocatable(),
                'qty' => Qty::fmt($i->qty_on_hand).' '.$i->item->baseUom->code,
                'avail' => Qty::fmt($i->qty_available),
            ])->values(),
        ]);
    }
}
