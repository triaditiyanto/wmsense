<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Location;
use App\Models\Warehouse;
use App\Services\StockControlService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inventory > Pindah Stok / Adjustment / Hold. Satu halaman (view inventory.control) dengan $mode: move | adjust | hold.
 * Endpoint JSON dipakai modal di halaman; lookup() juga dipakai Cek Stok mobile (/m/stock).
 */
class StockControlController extends Controller
{
    private const MODES = [
        'move' => ['code' => 'INV_MOVE', 'title' => 'Pindah Stok', 'types' => [MovementType::MOVE_OUT, MovementType::MOVE_IN]],
        'adjust' => ['code' => 'INV_ADJUST', 'title' => 'Adjustment', 'types' => [MovementType::ADJUST_IN, MovementType::ADJUST_OUT]],
        'hold' => ['code' => 'INV_HOLD', 'title' => 'Hold & Release', 'types' => [MovementType::HOLD, MovementType::RELEASE]],
    ];

    public function __construct(private StockControlService $service) {}

    public function index(Request $request, string $mode): View
    {
        $m = self::MODES[$mode];
        $warehouses = Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code']);
        $wh = $request->integer('warehouse') ?: ($warehouses->first()?->id);
        $q = $request->input('q');
        $tab = $request->input('tab') === 'history' ? 'history' : 'stock';

        return view('inventory.control', [
            'mode' => $mode, 'title' => $m['title'], 'code' => $m['code'], 'tab' => $tab,
            'warehouses' => $warehouses, 'warehouseId' => $wh,
            'rows' => $tab === 'stock' ? $this->service->search($wh, $q, 80) : collect(),
            'history' => $tab === 'history'
                ? InventoryMovement::with(['item', 'location', 'lpn', 'user'])->whereIn('type', array_map(fn ($t) => $t->value, $m['types']))
                    ->when($wh, fn ($w) => $w->where('warehouse_id', $wh))->latest('id')->limit(50)->get()
                : collect(),
            'adjustReasons' => StockControlService::ADJUST_REASONS,
            'holdReasons' => StockControlService::HOLD_REASONS,
        ]);
    }

    public function move(Request $request): JsonResponse
    {
        $d = $request->validate([
            'inventory_id' => ['required', 'integer'], 'to_code' => ['required', 'string', 'max:50'],
            'warehouse_id' => ['required', 'integer'], 'qty' => ['nullable', 'numeric', 'gt:0'],
            'confirm' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['to_code' => 'lokasi tujuan']);
        $to = Location::where('warehouse_id', $d['warehouse_id'])->where('code', strtoupper(trim($d['to_code'])))->first();
        abort_unless($to, 422, "Lokasi {$d['to_code']} tidak ditemukan di gudang ini.");

        return response()->json($this->service->move($d['inventory_id'], $to->id, isset($d['qty']) ? (string) $d['qty'] : null, $request->user(), (bool) ($d['confirm'] ?? false), $d['notes'] ?? null));
    }

    public function adjust(Request $request): JsonResponse
    {
        $d = $request->validate([
            'inventory_id' => ['required', 'integer'], 'new_qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->service->adjust($d['inventory_id'], (string) $d['new_qty'], $d['reason'], $d['notes'] ?? null, $request->user()));
    }

    public function adjustNew(Request $request): JsonResponse
    {
        $d = $request->validate([
            'warehouse_id' => ['required', 'integer'], 'location_code' => ['required', 'string', 'max:50'], 'sku' => ['required', 'string', 'max:60'],
            'qty' => ['required', 'numeric', 'gt:0'], 'lot_no' => ['nullable', 'string', 'max:60'], 'expiry_date' => ['nullable', 'date'],
            'reason' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $loc = Location::where('warehouse_id', $d['warehouse_id'])->where('code', strtoupper(trim($d['location_code'])))->first();
        abort_unless($loc, 422, "Lokasi {$d['location_code']} tidak ditemukan di gudang ini.");
        $item = Item::where('sku', trim($d['sku']))->orWhere('barcode', trim($d['sku']))->first();
        abort_unless($item, 422, "Item {$d['sku']} tidak ditemukan.");

        return response()->json($this->service->adjustNew($loc->id, $item->id, (string) $d['qty'], $d['lot_no'] ?? null, $d['expiry_date'] ?? null, $d['reason'], $d['notes'] ?? null, $request->user()));
    }

    public function hold(Request $request): JsonResponse
    {
        $d = $request->validate(['inventory_id' => ['required', 'integer'], 'qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:255']]);

        return response()->json($this->service->hold($d['inventory_id'], (string) $d['qty'], $d['reason'], $d['notes'] ?? null, $request->user()));
    }

    public function release(Request $request): JsonResponse
    {
        $d = $request->validate(['inventory_id' => ['required', 'integer'], 'qty' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:255']]);

        return response()->json($this->service->release($d['inventory_id'], (string) $d['qty'], $d['notes'] ?? null, $request->user()));
    }

    /** Cek stok (scan / ketik lokasi, LPN, SKU, lot) -> JSON ringkas, dipakai mobile. */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        if ($q === '') {
            return response()->json(['rows' => []]);
        }
        $rows = $this->service->search($request->integer('warehouse') ?: null, $q, 40);

        return response()->json(['rows' => $rows->map(fn ($i) => [
            'id' => $i->id, 'sku' => $i->item->sku, 'name' => $i->item->name, 'location' => $i->location->code, 'zone' => $i->location->zone?->code,
            'lpn' => $i->lpn?->lpn_no, 'lot' => $i->lot_no, 'expiry' => $i->expiry_date?->format('d/m/Y'),
            'status' => $i->status->label(), 'status_ok' => $i->status->value === 'AVAILABLE',
            'on_hand' => Qty::fmt($i->qty_on_hand), 'available' => Qty::fmt($i->qty_available),
            'allocated' => Qty::fmt($i->qty_allocated), 'hold' => Qty::fmt($i->qty_hold), 'uom' => $i->item->baseUom->code,
        ])->values()]);
    }
}
