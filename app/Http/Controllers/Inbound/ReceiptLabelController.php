<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\LpnStatus;
use App\Enums\LpnType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Master\LabelController;
use App\Models\Lpn;
use App\Models\ReceiptHeader;
use App\Models\ReceiptReceiving;
use App\Models\Warehouse;
use App\Services\LabelService;
use App\Services\LpnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cetak label LPN dari dokumen Receipt:
 *   print    : source=used -> LPN yang sudah dipakai menerima dokumen ini (pallet tag + isi + no. dokumen)
 *              source=ids  -> LPN tertentu (mis. hasil generate di bawah), wajib gudang yang sama
 *   generate : siapkan N LPN kosong untuk kedatangan dokumen ini (butuh INB_RECEIVING:create atau MST_LPN:create)
 */
class ReceiptLabelController extends Controller
{
    public function print(Request $request, ReceiptHeader $receipt, LabelService $labels): View
    {
        $data = $request->validate([
            'source' => ['required', 'in:used,prepared,ids'],
            'ids' => ['required_if:source,ids', 'array', 'max:'.LabelService::MAX_LABELS],
            'ids.*' => ['integer'],
            ...LabelController::optionRules(),
        ]);

        $ids = match ($data['source']) {
            'used' => ReceiptReceiving::where('receipt_header_id', $receipt->id)->whereNull('cancelled_at')->distinct()->pluck('lpn_id'),
            'prepared' => self::preparedQuery($receipt)->pluck('id'),
            'ids' => Lpn::where('warehouse_id', $receipt->warehouse_id)->whereIn('id', $data['ids'])->pluck('id'),
        };

        if ($ids->isEmpty()) {
            throw new BusinessException(match ($data['source']) {
                'used' => 'Belum ada LPN yang dipakai menerima dokumen ini.',
                'prepared' => 'Belum ada LPN kosong yang disiapkan untuk dokumen ini.',
                default => 'LPN tidak ditemukan di gudang dokumen ini.',
            });
        }

        $result = $labels->lpnLabels($ids, $data, $receipt);
        if (! $request->boolean('preview')) {
            $labels->markPrinted('lpn', $ids, $result['opt']['copies']);
        }

        return view('labels.print', [...$result, 'preview' => $request->boolean('preview'), 'back' => route('inbound.receipts.show', $receipt)]);
    }

    public function generate(Request $request, ReceiptHeader $receipt, LpnService $lpns): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasAccess('INB_RECEIVING', 'create') && ! $user->hasAccess('MST_LPN', 'create')) {
            abort(403, 'Anda tidak punya akses membuat LPN.');
        }
        if (! in_array($receipt->status->value, ['DRAFT', 'OPEN', 'RECEIVING'], true)) {
            throw new BusinessException("Dokumen {$receipt->status->label()}: tidak perlu LPN baru lagi.");
        }
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.min(LpnService::MAX_GENERATE, 200)],
            'lpn_type' => ['required', Rule::enum(LpnType::class)],
            'pallet_id' => ['nullable', Rule::exists('master_pallet', 'id')->where('is_active', true)],
        ]);

        $created = $lpns->generate(Warehouse::findOrFail($receipt->warehouse_id), LpnType::from($data['lpn_type']), $data['pallet_id'] ?? null,
            (int) $data['count'], $user, 'Untuk '.$receipt->receipt_no);

        return response()->json([
            'message' => "{$created->count()} LPN disiapkan untuk {$receipt->receipt_no}.",
            'ids' => $created->pluck('id'),
        ]);
    }

    /** Jumlah LPN yang sudah dipakai & yang disiapkan (masih kosong) untuk dokumen ini. */
    public static function counts(ReceiptHeader $receipt): array
    {
        return [
            'used' => ReceiptReceiving::where('receipt_header_id', $receipt->id)->whereNull('cancelled_at')->distinct()->count('lpn_id'),
            'prepared' => self::preparedQuery($receipt)->count(),
        ];
    }

    /** LPN kosong yang disiapkan untuk dokumen ini (generate dari tombol cetak receipt). */
    private static function preparedQuery(ReceiptHeader $receipt)
    {
        return Lpn::where('warehouse_id', $receipt->warehouse_id)->where('status', LpnStatus::EMPTY)
            ->where('notes', 'Untuk '.$receipt->receipt_no);
    }
}
