<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\LocationFunction;
use App\Enums\ReceiptStatus;
use App\Enums\ReceivingCondition;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ReceiptHeader;
use App\Models\ReceiptReceiving;
use App\Models\Warehouse;
use App\Services\ReceivingService;
use App\Support\Qty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Receiving: halaman desktop + endpoint JSON yang juga dipakai tampilan mobile (/m).
 */
class ReceivingController extends Controller
{
    public function __construct(private ReceivingService $service)
    {
    }

    // =================================================================== halaman desktop

    /** Antrian dokumen yang siap / sedang diterima. */
    public function index(Request $request): View
    {
        return view('inbound.receiving.index', [
            'receipts' => $this->queue($request)->paginate(30)->withQueryString(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function show(ReceiptHeader $receipt): View
    {
        $receipt->load([
            'warehouse', 'owner', 'supplier', 'customer',
            'receivings' => fn ($q) => $q->with(['item', 'uom', 'lpn', 'location', 'receivedBy', 'cancelledBy']),
            'discrepancies' => fn ($q) => $q->with(['item.baseUom', 'detail']),
        ]);

        return view('inbound.receiving.show', [
            'receipt' => $receipt,
            'progress' => $this->service->progress($receipt),
            'docks' => $this->docks($receipt->warehouse_id),
            'conditions' => ReceivingCondition::cases(),
        ]);
    }

    // =================================================================== JSON (desktop & mobile)

    public function progress(ReceiptHeader $receipt): JsonResponse
    {
        return response()->json($this->service->progress($receipt));
    }

    /** Kapasitas dock + saran dock. ?current=id dock yang sedang dipakai. */
    public function dockStatus(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        return response()->json($this->service->dockStatus($receipt, $request->integer('current') ?: null));
    }

    /** Semua dock penuh -> beri tahu admin. */
    public function notifyDockFull(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $count = $this->service->notifyDockFull($receipt, $request->user());

        return response()->json(['message' => "Notifikasi terkirim ke {$count} admin/supervisor. Tunggu dock dikosongkan."]);
    }

    public function resolveLocation(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);
        $loc = $this->service->resolveLocation($receipt, $request->code);

        return response()->json(['id' => $loc->id, 'code' => $loc->code]);
    }

    public function resolveLpn(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']]);

        return response()->json($this->service->inspectLpn($receipt, $request->code));
    }

    public function resolveItem(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);

        return response()->json($this->service->resolveItem($receipt, $request->code));
    }

    public function receive(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'lpn_no' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'item_id' => ['required', 'integer'],
            'uom_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lot_no' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date'],
            'condition' => ['required', Rule::enum(ReceivingCondition::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'confirm' => ['nullable', 'boolean'],
        ], [], ['location_id' => 'lokasi dock', 'lpn_no' => 'LPN', 'item_id' => 'item', 'qty' => 'qty', 'lot_no' => 'lot', 'expiry_date' => 'expired', 'condition' => 'kondisi']);

        $source = $request->input('source') === 'MOBILE' ? ReceiptReceiving::SOURCE_MOBILE : ReceiptReceiving::SOURCE_DESKTOP;
        $line = $this->service->receive($receipt, $data, $request->user(), $source);
        $line->load(['item', 'uom', 'lpn']);

        $message = 'Diterima '.Qty::fmt($line->qty)." {$line->uom->code} {$line->item->sku} ke LPN {$line->lpn->lpn_no}.";
        if ($source === ReceiptReceiving::SOURCE_DESKTOP) {
            session()->flash('success', $message);
        }

        return response()->json([
            'message' => $message,
            'line' => [
                'id' => $line->id, 'sku' => $line->item->sku, 'name' => $line->item->name, 'qty' => (float) $line->qty,
                'uom' => $line->uom->code, 'lpn_no' => $line->lpn->lpn_no, 'lot_no' => $line->lot_no,
                'expiry_date' => $line->expiry_date?->format('d/m/Y'), 'condition' => $line->condition->label(),
                'time' => $line->received_at->format('H:i'),
            ],
            'progress' => $this->service->progress($receipt),
            // status dock terbaru: klien mengarahkan LPN berikutnya ke dock lain bila dock ini penuh
            'dock' => $this->service->dockStatus($receipt, $line->location_id),
        ]);
    }

    /** {receiving} = baris o_receipt_receiving milik {receipt} (scopeBindings -> relasi receivings). */
    public function cancelLine(Request $request, ReceiptHeader $receipt, ReceiptReceiving $receiving): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->service->cancelLine($receiving, $request->user(), $data['reason'] ?? null);

        return $this->ok("Baris terima {$receiving->item->sku} dibatalkan, stok dikembalikan.");
    }

    public function finish(Request $request, ReceiptHeader $receipt): JsonResponse
    {
        $count = $this->service->finish($receipt, $request->user());

        return $this->ok($count
            ? "Penerimaan {$receipt->receipt_no} selesai dengan {$count} selisih. Lihat tab Selisih."
            : "Penerimaan {$receipt->receipt_no} selesai, sesuai ASN.");
    }

    // =================================================================== helper (juga dipakai MobileController)

    public function queue(Request $request)
    {
        return ReceiptHeader::query()
            ->with(['warehouse', 'owner', 'supplier', 'customer'])
            ->withCount('details')
            ->withSum('details as expected_sum', 'qty_expected_base')
            ->withSum('details as received_sum', 'qty_received_base')
            ->whereIn('status', [ReceiptStatus::OPEN, ReceiptStatus::RECEIVING])
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('receipt_no', 'like', '%'.$request->q.'%')
                ->orWhere('reference_no', 'like', '%'.$request->q.'%')
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$request->q.'%'))))
            ->orderByRaw("CASE WHEN status = 'RECEIVING' THEN 0 ELSE 1 END")
            ->orderBy('expected_date')
            ->orderBy('id');
    }

    public function docks(int $warehouseId)
    {
        return Location::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'ACTIVE')
            ->whereHas('locationClass.locationType', fn ($q) => $q->where('location_function', LocationFunction::RECEIVING))
            ->orderBy('code')
            ->get(['id', 'code']);
    }
}
