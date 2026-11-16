<?php

namespace App\Http\Controllers\Outbound;

use App\Enums\RotationRule;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\ShipmentHeader;
use App\Models\Warehouse;
use App\Services\ShipmentService;
use App\Services\WaveService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Shipment entry (header). Detail di ShipmentDetailController. Go Wave (1 shipment) memanggil WaveService. */
class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function index(Request $request): View
    {
        $shipments = ShipmentHeader::query()
            ->with(['warehouse', 'owner', 'customer', 'wave', 'deliveryOrder'])
            ->withCount('details')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->input('q');
                $query->where(fn ($w) => $w->where('shipment_no', 'like', "%{$q}%")
                    ->orWhere('reference_no', 'like', "%{$q}%")
                    ->orWhere('ship_to_name', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('outbound.shipments.index', ['shipments' => $shipments, ...$this->formOptions()]);
    }

    public function store(Request $request): JsonResponse
    {
        $shipment = $this->service->createHeader($this->validated($request), $request->user());

        return $this->ok("Shipment {$shipment->shipment_no} dibuat. Silakan tambahkan item.", route('outbound.shipments.show', $shipment));
    }

    public function show(ShipmentHeader $shipment): View
    {
        $shipment->load(['warehouse', 'owner', 'customer.addresses', 'address', 'wave', 'deliveryOrder', 'createdBy', 'confirmedBy', 'cancelledBy',
            'details.item.baseUom', 'details.uom', 'allocations.location', 'allocations.lpn', 'allocations.item', 'allocations.stagingLocation', 'allocations.picker']);

        return view('outbound.shipments.show', [
            'shipment' => $shipment,
            'allocations' => $shipment->allocations->where('status', '!=', 'CANCELLED')->sortBy(fn ($a) => [$a->shipment_detail_id, $a->location->pick_sequence ?? 0, $a->id])->values(),
            'totals' => [
                'ordered' => $shipment->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_ordered_base), '0'),
                'allocated' => $shipment->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_allocated_base), '0'),
                'picked' => $shipment->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_picked_base), '0'),
                'shipped' => $shipment->details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_shipped_base), '0'),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, ShipmentHeader $shipment): JsonResponse
    {
        $this->service->updateHeader($shipment, $this->validated($request), $request->user());

        return $this->ok('Header shipment berhasil diperbarui.');
    }

    public function confirm(Request $request, ShipmentHeader $shipment): JsonResponse
    {
        $this->service->confirm($shipment, $request->user());

        return $this->ok("Shipment {$shipment->shipment_no} dikonfirmasi (Open). Masukkan ke wave untuk dialokasikan.");
    }

    public function cancel(Request $request, ShipmentHeader $shipment): JsonResponse
    {
        $this->service->cancel($shipment, $request->user());

        return $this->ok("Shipment {$shipment->shipment_no} dibatalkan.");
    }

    /** Go Wave: wave berisi shipment ini saja, langsung dialokasikan dengan aturan yang dipilih. */
    public function goWave(Request $request, ShipmentHeader $shipment, WaveService $waves): JsonResponse
    {
        $data = $request->validate(['allocation_rule' => ['nullable', Rule::enum(RotationRule::class)]]);
        ['wave' => $wave, 'summary' => $summary] = $waves->goWave($shipment, $data['allocation_rule'] ?? null, $request->user());

        $msg = "Go Wave {$wave->wave_no}: {$summary['lines']} baris teralokasi (".Qty::fmt($summary['qty']).' base).';
        if ($summary['short']) {
            $msg .= ' Kurang stok: '.collect($summary['short'])->map(fn ($s) => "{$s['sku']} ".Qty::fmt($s['qty']))->implode(', ').'.';
        }

        return $this->ok($msg);
    }

    private function validated(Request $request): array
    {
        $ownerId = $request->integer('owner_id');

        return $request->validate([
            'warehouse_id' => ['required', Rule::exists('master_warehouse', 'id')->where('is_active', true)],
            'owner_id' => ['required', Rule::exists('master_owner', 'id')->where('is_active', true)],
            'customer_id' => ['required', Rule::exists('master_customer', 'id')->where(fn ($q) => $q->where('is_active', true)
                ->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $ownerId)))],
            'customer_address_id' => ['nullable', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'order_date' => ['nullable', 'date'],
            'expected_ship_date' => ['nullable', 'date'],
            'allocation_rule' => ['nullable', Rule::enum(RotationRule::class)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9'],
            'notes' => ['nullable', 'string'],
        ], ['customer_id.exists' => 'Customer tidak aktif atau bukan milik owner ini.'], [
            'customer_id' => 'customer', 'customer_address_id' => 'alamat kirim', 'reference_no' => 'no. referensi',
            'order_date' => 'tanggal order', 'expected_ship_date' => 'tanggal kirim', 'allocation_rule' => 'aturan alokasi', 'priority' => 'prioritas',
        ]);
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'owners' => Owner::where('is_active', true)->orderBy('code')->get(),
            'rules' => RotationRule::cases(),
            'statuses' => ShipmentStatus::cases(),
        ];
    }
}
