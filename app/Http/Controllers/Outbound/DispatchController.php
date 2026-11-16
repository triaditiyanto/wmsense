<?php

namespace App\Http\Controllers\Outbound;

use App\Enums\DeliveryOrderStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryOrderHeader;
use App\Models\ShipmentHeader;
use App\Models\Warehouse;
use App\Services\DispatchService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dispatch: tab "Siap kirim" = shipment PICKED belum masuk DO (centang -> Buat Surat Jalan),
 * tab "Surat Jalan" = daftar DO. Halaman DO: shipment anggota, ubah header, cetak, Kirim, batal.
 */
class DispatchController extends Controller
{
    public function __construct(private DispatchService $service) {}

    public function index(Request $request): View
    {
        $tab = $request->input('tab') === 'do' ? 'do' : 'ready';
        $whId = $request->integer('warehouse') ?: null;

        return view('outbound.dispatch.index', [
            'tab' => $tab,
            'ready' => $tab === 'ready' ? ShipmentHeader::query()->with(['customer', 'owner', 'warehouse', 'wave'])
                ->withSum('details as picked_sum', 'qty_picked_base')->withCount('details')
                ->where('status', ShipmentStatus::PICKED)->whereNull('delivery_order_id')
                ->when($whId, fn ($q) => $q->where('warehouse_id', $whId))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('shipment_no', 'like', "%{$request->q}%")
                    ->orWhere('ship_to_name', 'like', "%{$request->q}%")->orWhere('reference_no', 'like', "%{$request->q}%")))
                ->orderBy('expected_ship_date')->orderBy('id')->get() : null,
            'dos' => $tab === 'do' ? DeliveryOrderHeader::query()->with(['warehouse', 'createdBy', 'shipments'])->withCount('shipments')
                ->when($whId, fn ($q) => $q->where('warehouse_id', $whId))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('do_no', 'like', "%{$request->q}%")
                    ->orWhere('vehicle_no', 'like', "%{$request->q}%")->orWhere('driver_name', 'like', "%{$request->q}%")
                    ->orWhereHas('shipments', fn ($s) => $s->where('shipment_no', 'like', "%{$request->q}%"))))
                ->latest('id')->paginate(30)->withQueryString() : null,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'statuses' => DeliveryOrderStatus::cases(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_ids' => ['nullable', 'array'], 'shipment_ids.*' => ['integer'],
            'warehouse_id' => ['nullable', 'integer', 'exists:master_warehouse,id'],
            ...$this->headerRules(),
        ]);
        $ids = $data['shipment_ids'] ?? [];
        $do = $this->service->createDo($data, $ids, $request->user());

        return $this->ok("Surat Jalan {$do->do_no} dibuat".($ids ? ' ('.count($ids).' shipment).' : ' (kosong). Tambahkan shipment siap kirim.'), route('outbound.dispatch.show', $do));
    }

    public function show(DeliveryOrderHeader $do): View
    {
        $do->load(['warehouse', 'createdBy', 'dispatchedBy', 'shipments.customer', 'shipments.details.item.baseUom', 'shipments.details.uom']);
        $addable = $do->status === DeliveryOrderStatus::DRAFT
            ? ShipmentHeader::with('customer')->where('status', ShipmentStatus::PICKED)->whereNull('delivery_order_id')->where('warehouse_id', $do->warehouse_id)->orderBy('id')->get()
            : collect();

        return view('outbound.dispatch.show', ['do' => $do, 'addable' => $addable, 'totals' => $this->totals($do)]);
    }

    public function update(Request $request, DeliveryOrderHeader $do): JsonResponse
    {
        $this->service->updateDo($do, $request->validate($this->headerRules()));

        return $this->ok('Header Surat Jalan diperbarui.');
    }

    public function addShipments(Request $request, DeliveryOrderHeader $do): JsonResponse
    {
        $data = $request->validate(['shipment_ids' => ['required', 'array', 'min:1'], 'shipment_ids.*' => ['integer']], ['shipment_ids.required' => 'Pilih minimal satu shipment.']);
        $this->service->addShipments($do, $data['shipment_ids'], $request->user());

        return $this->ok(count($data['shipment_ids']).' shipment ditambahkan ke Surat Jalan.');
    }

    public function removeShipment(Request $request, DeliveryOrderHeader $do, ShipmentHeader $shipment): JsonResponse
    {
        $this->service->removeShipment($do, $shipment, $request->user());

        return $this->ok("Shipment {$shipment->shipment_no} dikeluarkan dari Surat Jalan.");
    }

    public function dispatch(Request $request, DeliveryOrderHeader $do): JsonResponse
    {
        $this->service->dispatch($do, $request->user());

        return $this->ok("Surat Jalan {$do->do_no} dikirim. Stok keluar dari gudang.");
    }

    public function cancel(Request $request, DeliveryOrderHeader $do): JsonResponse
    {
        $this->service->cancel($do, $request->user());

        return $this->ok("Surat Jalan {$do->do_no} dibatalkan. Shipment kembali ke daftar siap kirim.");
    }

    /** Halaman cetak Surat Jalan (dialog print browser). */
    public function print(DeliveryOrderHeader $do): View
    {
        $do->load(['warehouse', 'shipments.customer', 'shipments.owner', 'shipments.details.item.baseUom', 'shipments.details.uom', 'dispatchedBy', 'createdBy']);
        $this->service->markPrinted($do);

        return view('outbound.dispatch.print', ['do' => $do, 'totals' => $this->totals($do)]);
    }

    private function headerRules(): array
    {
        return [
            'ship_date' => ['nullable', 'date'], 'vehicle_no' => ['nullable', 'string', 'max:20'], 'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:30'], 'carrier' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string'],
        ];
    }

    private function totals(DeliveryOrderHeader $do): array
    {
        $lines = 0;
        $qty = '0';
        foreach ($do->shipments as $s) {
            foreach ($s->details as $d) {
                $q = $do->status === DeliveryOrderStatus::DISPATCHED ? $d->qty_shipped_base : $d->qty_picked_base;
                if (Qty::cmp($q, 0) > 0) {
                    $lines++;
                    $qty = Qty::add($qty, $q);
                }
            }
        }

        return ['lines' => $lines, 'qty' => $qty];
    }
}
