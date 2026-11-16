<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\LocationFunction;
use App\Enums\ReceiptStatus;
use App\Enums\ReceivingCondition;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ReceiptReceiving;
use App\Models\Warehouse;
use App\Services\ReceivingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Intransit In: barang yang SUDAH diterima tapi BELUM disimpan (masih di lokasi dock / fungsi RECEIVING).
 * Sumber data = baris o_receipt_receiving yang belum dibatalkan dan lokasinya masih dock.
 * Batal terima di sini memakai ReceivingService::cancelLine (stok dock dikurangi, qty kembali ke "belum diterima",
 * dokumen RECEIVED dibuka ulang ke RECEIVING sehingga muncul lagi di antrian Receiving).
 */
class IntransitController extends Controller
{
    /** Barang di dock lebih lama dari ini ditandai "lama" (seharusnya segera di-putaway). */
    public const STALE_HOURS = 4;

    public function __construct(private ReceivingService $service) {}

    public function index(Request $request): View
    {
        $base = $this->query($request);

        $summary = (clone $base)->reorder()->selectRaw('COUNT(*) as line_count, COUNT(DISTINCT lpn_id) as lpn_count,
                COUNT(DISTINCT receipt_header_id) as receipt_count, MIN(received_at) as oldest,
                SUM(CASE WHEN received_at < ? THEN 1 ELSE 0 END) as stale_count', [now()->subHours(self::STALE_HOURS)])
            ->toBase()->first();

        // jumlah pallet per dock (tanpa filter dock, supaya kartu dock tetap lengkap)
        $perDock = $this->query($request->duplicate([...$request->query(), 'location' => null]))->reorder()
            ->selectRaw('location_id, COUNT(DISTINCT lpn_id) as lpn_count')->groupBy('location_id')->toBase()
            ->pluck('lpn_count', 'location_id');

        return view('inbound.intransit.index', [
            'lines' => $base->with(['header.supplier', 'header.customer', 'item', 'uom', 'lpn', 'location', 'receivedBy'])
                ->paginate(50)->withQueryString(),
            'summary' => $summary,
            'perDock' => $perDock,
            'docks' => Location::query()
                ->whereHas('locationClass.locationType', fn ($q) => $q->where('location_function', LocationFunction::RECEIVING))
                ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
                ->orderBy('code')->get(['id', 'code', 'warehouse_id']),
            'warehouses' => Warehouse::orderBy('code')->get(['id', 'code', 'name']),
            'conditions' => ReceivingCondition::cases(),
            'staleHours' => self::STALE_HOURS,
        ]);
    }

    /** Batal terima beberapa baris sekaligus. Alasan wajib (tercatat di kartu stok & baris terima). */
    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['ids' => 'baris', 'reason' => 'alasan']);

        $lines = ReceiptReceiving::with(['header', 'item', 'lpn'])
            ->whereIn('id', $data['ids'])->whereNull('cancelled_at')->whereNull('putaway_at')->orderBy('id')->get();
        if ($lines->isEmpty()) {
            throw new BusinessException('Baris yang dipilih sudah tidak ada / sudah dibatalkan. Muat ulang halaman.');
        }

        $r = $this->service->cancelLines($lines, $request->user(), $data['reason']);
        if ($r['done'] === 0) {
            throw new BusinessException('Tidak ada yang dibatalkan. '.implode(' ', $r['failed']));
        }

        $msg = "{$r['done']} baris dibatalkan, qty kembali ke Receiving.";
        if ($r['reopened']) {
            $msg .= ' Dokumen dibuka ulang: '.implode(', ', $r['reopened']).'.';
        }
        if ($r['failed']) {
            $msg .= ' Gagal '.count($r['failed']).': '.implode('; ', $r['failed']);
        }

        return $this->ok($msg);
    }

    /** Baris terima yang barangnya masih di dock. Urut terlama dulu (yang paling perlu di-putaway). */
    private function query(Request $request): Builder
    {
        $q = trim((string) $request->input('q'));

        return ReceiptReceiving::query()
            ->whereNull('cancelled_at')
            ->whereNull('putaway_at')
            ->whereHas('header', fn ($h) => $h
                ->whereIn('status', [ReceiptStatus::RECEIVING, ReceiptStatus::RECEIVED])
                ->when($request->filled('warehouse'), fn ($w) => $w->where('warehouse_id', $request->integer('warehouse'))))
            ->whereHas('location.locationClass.locationType', fn ($t) => $t->where('location_function', LocationFunction::RECEIVING))
            ->when($request->filled('location'), fn ($w) => $w->where('location_id', $request->integer('location')))
            ->when($request->filled('condition'), fn ($w) => $w->where('condition', $request->input('condition')))
            ->when($request->input('age') === 'stale', fn ($w) => $w->where('received_at', '<', now()->subHours(self::STALE_HOURS)))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->whereHas('header', fn ($h) => $h->where('receipt_no', 'like', "%{$q}%")->orWhere('reference_no', 'like', "%{$q}%"))
                ->orWhereHas('lpn', fn ($l) => $l->where('lpn_no', 'like', "%{$q}%"))
                ->orWhereHas('item', fn ($i) => $i->where('sku', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
                ->orWhere('lot_no', 'like', "%{$q}%")))
            ->orderBy('received_at')->orderBy('id');
    }
}
