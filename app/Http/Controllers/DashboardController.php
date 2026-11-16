<?php

namespace App\Http\Controllers;

use App\Enums\ReceiptStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReceiptHeader;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Controller dengan satu aksi: method __invoke, route cukup DashboardController::class */
    public function __invoke(): View
    {
        $receiptCounts = ReceiptHeader::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'receiptCounts' => $receiptCounts,
            'statuses' => ReceiptStatus::cases(),
            'itemCount' => Item::where('is_active', true)->count(),
            'locationCount' => Location::count(),
        ]);
    }
}
