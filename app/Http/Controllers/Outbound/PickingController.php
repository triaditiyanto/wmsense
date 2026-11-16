<?php

namespace App\Http\Controllers\Outbound;

use App\Enums\WaveStatus;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Warehouse;
use App\Models\WaveHeader;
use App\Services\PickingService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Picking desktop (Outbound > Picking) + endpoint JSON yang dipakai juga oleh /m/picking. */
class PickingController extends Controller
{
    public function __construct(private PickingService $service) {}

    public function index(Request $request): View
    {
        $tab = $request->input('tab') === 'history' ? 'history' : 'queue';

        return view('outbound.picking.index', [
            'tab' => $tab,
            'queue' => $this->service->queue($request->integer('warehouse') ?: null, $request->input('q')),
            'history' => $tab === 'history' ? WaveHeader::query()->whereIn('status', [WaveStatus::PICKED, WaveStatus::CANCELLED])
                ->with(['shipments', 'stagingLocation'])
                ->withCount(['allocations as done_count' => fn ($a) => $a->whereIn('status', ['PICKED', 'SHORT']), 'allocations as short_count' => fn ($a) => $a->where('status', 'SHORT')])
                ->withSum('allocations as picked_sum', 'qty_picked_base')
                ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
                ->when($request->filled('q'), fn ($q) => $q->where('wave_no', 'like', "%{$request->q}%"))
                ->latest('picked_at')->latest('id')->paginate(30)->withQueryString() : null,
            'warehouses' => Warehouse::orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function show(WaveHeader $wave): JsonResponse
    {
        return response()->json($this->service->payload($wave));
    }

    /** Cari wave dari nomor (scan) -> payload. */
    public function find(Request $request): JsonResponse
    {
        $data = $request->validate(['wave_no' => ['required', 'string', 'max:50']]);
        $wave = WaveHeader::where('wave_no', strtoupper(trim($data['wave_no'])))->first();
        if (! $wave) {
            return response()->json(['message' => "Wave {$data['wave_no']} tidak ditemukan."], 422);
        }
        if (! $wave->status->canPick()) {
            return response()->json(['message' => "Wave {$wave->wave_no} berstatus {$wave->status->label()}."], 422);
        }

        return response()->json($this->service->payload($wave));
    }

    public function start(Request $request, WaveHeader $wave): JsonResponse
    {
        $data = $request->validate(['staging_location_id' => ['nullable', 'integer'], 'source' => ['nullable', 'in:DESKTOP,MOBILE']]);
        $wave = $this->service->start($wave, $request->user(), $data['staging_location_id'] ?? null, $data['source'] ?? 'DESKTOP');

        return response()->json(['message' => "Picking {$wave->wave_no} dimulai. Staging: {$wave->stagingLocation->code}.", ...$this->service->payload($wave)]);
    }

    public function pick(Request $request, Allocation $line): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'numeric', 'min:0'],
            'staging_location_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'confirm' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'in:DESKTOP,MOBILE'],
        ]);
        $line = $this->service->pick($line, $data['qty'], $request->user(), $data['source'] ?? 'DESKTOP', $data['idempotency_key'] ?? null,
            $data['staging_location_id'] ?? null, (bool) ($data['confirm'] ?? false), $data['reason'] ?? null);
        $line->load(['item', 'location', 'wave']);

        return response()->json([
            'message' => "{$line->item->sku} ".Qty::fmt($line->qty_picked_base).' dipick dari '.$line->location->code.($line->status->value === 'SHORT' ? ' (kurang)' : '').'.',
            'line' => ['id' => $line->id, 'status' => $line->status->value, 'qty_picked' => Qty::fmt($line->qty_picked_base)],
            'wave_status' => $line->wave->status->value,
        ]);
    }

    public function finish(Request $request, WaveHeader $wave): JsonResponse
    {
        $data = $request->validate(['confirm' => ['nullable', 'boolean'], 'source' => ['nullable', 'in:DESKTOP,MOBILE']]);
        $wave = $this->service->finish($wave, $request->user(), (bool) ($data['confirm'] ?? false));
        $msg = "Picking {$wave->wave_no} selesai. Shipment siap kirim ada di menu Dispatch.";
        if (($data['source'] ?? 'DESKTOP') !== 'MOBILE') {
            session()->flash('success', $msg);
        }

        return response()->json(['message' => $msg, 'status' => $wave->status->value]);
    }
}
