<?php

namespace App\Http\Controllers\Outbound;

use App\Enums\AllocationStatus;
use App\Enums\RotationRule;
use App\Enums\ShipmentStatus;
use App\Enums\WaveStatus;
use App\Http\Controllers\Controller;
use App\Models\ShipmentHeader;
use App\Models\Warehouse;
use App\Models\WaveHeader;
use App\Services\AllocationService;
use App\Services\WaveService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Wave Planning: tab "Rencana" = shipment OPEN yang bisa digabung (centang -> Buat Wave),
 * tab "Wave" = daftar wave. Halaman wave: shipment anggota, hasil alokasi, Run Wave / alokasi ulang / batal.
 */
class WaveController extends Controller
{
    public function __construct(private WaveService $service) {}

    public function index(Request $request): View
    {
        $tab = $request->input('tab') === 'waves' ? 'waves' : 'plan';
        $whId = $request->integer('warehouse') ?: null;

        return view('outbound.waves.index', [
            'tab' => $tab,
            'open' => $tab === 'plan' ? ShipmentHeader::query()->with(['customer', 'owner', 'warehouse'])->withCount('details')
                ->withSum('details as qty_sum', 'qty_ordered_base')
                ->where('status', ShipmentStatus::OPEN)
                ->when($whId, fn ($q) => $q->where('warehouse_id', $whId))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('shipment_no', 'like', "%{$request->q}%")
                    ->orWhere('ship_to_name', 'like', "%{$request->q}%")->orWhere('reference_no', 'like', "%{$request->q}%")))
                ->orderBy('priority')->orderBy('expected_ship_date')->orderBy('id')->get() : null,
            'waves' => $tab === 'waves' ? WaveHeader::query()->with(['createdBy', 'warehouse'])
                ->withCount(['shipments', 'allocations as open_count' => fn ($a) => $a->where('status', AllocationStatus::OPEN)])
                ->when($whId, fn ($q) => $q->where('warehouse_id', $whId))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('wave_no', 'like', "%{$request->q}%")
                    ->orWhereHas('shipments', fn ($s) => $s->where('shipment_no', 'like', "%{$request->q}%"))))
                ->latest('id')->paginate(30)->withQueryString() : null,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'rules' => RotationRule::cases(),
            'statuses' => WaveStatus::cases(),
        ]);
    }

    /** Buat wave dari shipment terpilih (+ aturan alokasi opsional). run=1 -> langsung dialokasikan. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['integer'],
            'allocation_rule' => ['nullable', Rule::enum(RotationRule::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'run' => ['nullable', 'boolean'],
        ], ['shipment_ids.required' => 'Pilih minimal satu shipment.'], ['allocation_rule' => 'aturan alokasi']);
        $wave = $this->service->create($data, $data['shipment_ids'], $request->user());
        $msg = "Wave {$wave->wave_no} dibuat ({$wave->shipments()->count()} shipment).";
        if ($data['run'] ?? false) {
            $msg .= ' '.$this->summaryText($this->service->allocate($wave, $request->user()));
        }

        return $this->ok($msg, route('outbound.waves.show', $wave));
    }

    public function show(WaveHeader $wave): View
    {
        $wave->load(['warehouse', 'createdBy', 'allocatedBy', 'stagingLocation', 'shipments.customer', 'shipments.details.item',
            'allocations.location', 'allocations.item.baseUom', 'allocations.lpn', 'allocations.shipment', 'allocations.detail']);
        $lines = $wave->allocations->where('status', '!=', AllocationStatus::CANCELLED)
            ->sortBy(fn ($a) => [$a->location->pick_sequence ?? PHP_INT_MAX, $a->location->code, $a->id])->values();

        // ringkasan per shipment: qty pesan / teralokasi / dipick
        $perShipment = $wave->shipments->map(fn ($s) => [
            'shipment' => $s,
            'ordered' => $s->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_ordered_base), '0'),
            'allocated' => $s->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_allocated_base), '0'),
            'picked' => $s->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_picked_base), '0'),
            'short' => $s->details->filter(fn ($d) => Qty::cmp($d->qtyOpenBase(), 0) > 0)->map(fn ($d) => $d->item->sku.' '.Qty::fmt($d->qtyOpenBase()))->implode(', '),
        ]);
        $addable = in_array($wave->status, [WaveStatus::PLANNED, WaveStatus::ALLOCATED], true) && $wave->wave_type === WaveHeader::TYPE_PLAN
            ? ShipmentHeader::with('customer')->where('status', ShipmentStatus::OPEN)->where('warehouse_id', $wave->warehouse_id)->orderBy('priority')->orderBy('id')->get()
            : collect();

        return view('outbound.waves.show', compact('wave', 'lines', 'perShipment', 'addable') + ['rules' => RotationRule::cases()]);
    }

    public function allocate(Request $request, WaveHeader $wave): JsonResponse
    {
        return $this->ok("Run Wave {$wave->wave_no}: ".$this->summaryText($this->service->allocate($wave, $request->user())));
    }

    public function reallocate(Request $request, WaveHeader $wave): JsonResponse
    {
        $data = $request->validate(['allocation_rule' => ['nullable', Rule::enum(RotationRule::class)]]);
        $rule = $request->has('allocation_rule') ? ($data['allocation_rule'] ?? '') : null;

        return $this->ok("Alokasi ulang {$wave->wave_no}: ".$this->summaryText($this->service->reallocate($wave, $request->user(), $rule)));
    }

    public function addShipments(Request $request, WaveHeader $wave): JsonResponse
    {
        $data = $request->validate(['shipment_ids' => ['required', 'array', 'min:1'], 'shipment_ids.*' => ['integer']],
            ['shipment_ids.required' => 'Pilih minimal satu shipment.']);
        $this->service->addShipments($wave, $data['shipment_ids'], $request->user());

        return $this->ok(count($data['shipment_ids']).' shipment ditambahkan ke wave. Jalankan Run Wave untuk mengalokasikan.');
    }

    public function removeShipment(Request $request, WaveHeader $wave, ShipmentHeader $shipment): JsonResponse
    {
        $this->service->removeShipment($wave, $shipment, $request->user());

        return $this->ok("Shipment {$shipment->shipment_no} dilepas dari wave (kembali Open).");
    }

    public function cancel(Request $request, WaveHeader $wave): JsonResponse
    {
        $this->service->cancel($wave, $request->user());

        return $this->ok("Wave {$wave->wave_no} dibatalkan. Semua shipment kembali Open.");
    }

    /** Simulasi urutan kandidat stok satu item menurut aturan (tanpa mengubah apa pun). */
    public function preview(Request $request, AllocationService $allocation): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'], 'item_id' => ['required', 'integer'], 'owner_id' => ['required', 'integer'],
            'rule' => ['required', Rule::enum(RotationRule::class)], 'lot_no' => ['nullable', 'string', 'max:50'],
        ]);
        $rows = $allocation->preview($data['warehouse_id'], $data['item_id'], $data['owner_id'], RotationRule::from($data['rule']), $data['lot_no'] ?? null);

        return response()->json($rows->map(fn ($i) => [
            'location' => $i->location->code, 'lpn' => $i->lpn?->lpn_no, 'lot_no' => $i->lot_no,
            'expiry_date' => $i->expiry_date?->format('d/m/Y'), 'received_at' => $i->received_at?->format('d/m/Y'),
            'qty_available' => Qty::fmt($i->qty_available),
        ])->values());
    }

    private function summaryText(array $s): string
    {
        $t = "{$s['lines']} baris teralokasi (".Qty::fmt($s['qty']).' base).';
        if ($s['short']) {
            $t .= ' Kurang stok: '.collect($s['short'])->map(fn ($x) => "{$x['shipment_no']} {$x['sku']} ".Qty::fmt($x['qty']))->implode(', ').'.';
        }

        return $t;
    }
}
