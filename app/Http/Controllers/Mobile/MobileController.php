<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\ReceivingCondition;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Inbound\ReceivingController;
use App\Models\ReceiptHeader;
use App\Services\PickingService;
use App\Services\PutawayService;
use App\Services\ReceivingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tampilan mobile untuk operator (/m). Logika & endpoint JSON memakai ReceivingController/Service yang sama
 * dengan desktop, jadi aturan bisnisnya identik.
 */
class MobileController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();

        return view('mobile.home', [
            'menus' => [
                ['label' => 'Penerimaan', 'icon' => 'box-arrow-in-down', 'route' => 'm.receiving.index', 'code' => 'INB_RECEIVING', 'ready' => true],
                ['label' => 'Putaway', 'icon' => 'arrow-down-up', 'route' => 'm.putaway', 'code' => 'INB_PUTAWAY', 'ready' => true],
                ['label' => 'Picking', 'icon' => 'cart-check', 'route' => 'm.picking', 'code' => 'OUT_PICKING', 'ready' => true],
                ['label' => 'Cek Stok', 'icon' => 'search', 'route' => 'm.stock', 'code' => 'INV_STOCK', 'ready' => true],
            ],
            'user' => $user,
        ]);
    }

    public function receivingIndex(Request $request, ReceivingController $receiving): View
    {
        return view('mobile.receiving.index', [
            'receipts' => $receiving->queue($request)->limit(50)->get(),
        ]);
    }

    /** Putaway (wizard satu halaman): scan LPN -> cek isi & saran -> scan lokasi tujuan. */
    public function putaway(PutawayService $service): View
    {
        return view('mobile.putaway.index', ['pending' => $service->pending()]);
    }

    /** Picking (wizard satu halaman): pilih wave -> per baris: scan lokasi -> scan barang + qty -> selesai. */
    public function picking(PickingService $service): View
    {
        return view('mobile.picking.index', ['queue' => $service->queue()]);
    }

    /** Cek stok: scan / ketik lokasi, LPN, SKU atau lot. */
    public function stock(): View
    {
        return view('mobile.stock.index');
    }

    public function receivingShow(ReceiptHeader $receipt, ReceivingService $service, ReceivingController $receiving): View
    {
        $receipt->load(['warehouse', 'owner', 'supplier', 'customer']);

        return view('mobile.receiving.show', [
            'receipt' => $receipt,
            'progress' => $service->progress($receipt),
            'docks' => $receiving->docks($receipt->warehouse_id),
            // status kapasitas awal -> kartu dock langsung tampil tanpa menunggu request pertama
            'dockStatus' => $service->dockStatus($receipt),
            'conditions' => ReceivingCondition::cases(),
        ]);
    }
}
