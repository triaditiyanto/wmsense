<?php

namespace App\Http\Controllers\Master;

use App\Enums\LocationFunction;
use App\Enums\LpnStatus;
use App\Enums\LpnType;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Lpn;
use App\Models\Pallet;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\LabelService;
use App\Services\LpnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master > Cetak Label: pilih LPN / lokasi -> atur kode (QR / barcode), ukuran & salinan -> halaman cetak.
 *   index    : daftar + centang (master/labels/{type}, type = lpn | location)
 *   print    : halaman label siap cetak (POST dari form, atau GET untuk pratinjau)
 *   generate : buat LPN baru lalu langsung cetak (butuh MST_LPN:create)
 */
class LabelController extends Controller
{
    public function __construct(private LabelService $labels)
    {
    }

    public function index(Request $request, string $type): View
    {
        $query = $type === 'lpn' ? $this->lpnQuery($request) : $this->locationQuery($request);
        $rows = $query->paginate(100)->withQueryString();

        return view('master.labels.index', [
            'type' => $type,
            'rows' => $rows,
            'total' => $rows->total(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'zones' => Zone::with('warehouse')->where('is_active', true)->orderBy('warehouse_id')->orderBy('code')->get(),
            'pallets' => Pallet::where('is_active', true)->orderBy('code')->get(),
            'sizes' => LabelService::sizesFor($type),
            'symbologies' => LabelService::SYMBOLOGIES,
            'maxLabels' => LabelService::MAX_LABELS,
            'maxGenerate' => LpnService::MAX_GENERATE,
        ]);
    }

    public function print(Request $request): View
    {
        $data = $request->validate([
            'type' => ['required', 'in:lpn,location'],
            'ids' => ['required_without:all', 'array', 'max:'.LabelService::MAX_LABELS],
            'ids.*' => ['integer'],
            'all' => ['nullable', 'boolean'],   // semua sesuai filter (parameter filter ikut dikirim)
            ...self::optionRules(),
        ]);

        $ids = ! empty($data['all'])
            ? ($data['type'] === 'lpn' ? $this->lpnQuery($request) : $this->locationQuery($request))
                ->limit(LabelService::MAX_LABELS + 1)->pluck('id')
            : collect($data['ids']);

        $result = $data['type'] === 'lpn'
            ? $this->labels->lpnLabels($ids, $data)
            : $this->labels->locationLabels($ids, $data);

        if (! $request->boolean('preview')) {
            $this->labels->markPrinted($data['type'], $ids, $result['opt']['copies']);
        }

        return view('labels.print', [...$result, 'preview' => $request->boolean('preview'), 'back' => url()->previous()]);
    }

    /** Generate LPN baru -> id untuk langsung dicetak. */
    public function generate(Request $request, LpnService $lpns): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('master_warehouse', 'id')->where('is_active', true)],
            'count' => ['required', 'integer', 'min:1', 'max:'.min(LpnService::MAX_GENERATE, LabelService::MAX_LABELS)],
            'lpn_type' => ['required', Rule::enum(LpnType::class)],
            'pallet_id' => ['nullable', Rule::exists('master_pallet', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $created = $lpns->generate(Warehouse::findOrFail($data['warehouse_id']), LpnType::from($data['lpn_type']),
            $data['pallet_id'] ?? null, (int) $data['count'], $request->user(), $data['notes'] ?? 'Generate dari Cetak Label');

        $message = "{$created->count()} LPN dibuat: {$created->first()->lpn_no} s/d {$created->last()->lpn_no}.";
        session()->flash('success', $message);

        return response()->json(['message' => $message, 'ids' => $created->pluck('id')]);
    }

    /** Aturan opsi cetak (dipakai juga oleh cetak label di Receipt). */
    public static function optionRules(): array
    {
        return [
            'symbology' => ['nullable', Rule::in(array_keys(LabelService::SYMBOLOGIES))],
            'size' => ['nullable', Rule::in(array_keys(LabelService::SIZES))],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
            'contents' => ['nullable', 'boolean'],
            'level_color' => ['nullable', 'boolean'],
            'preview' => ['nullable', 'boolean'],
        ];
    }

    private function lpnQuery(Request $request): Builder
    {
        return Lpn::query()
            ->with(['warehouse', 'location', 'pallet'])
            ->when($request->filled('q'), fn ($q) => $q->where('lpn_no', 'like', '%'.strtoupper($request->q).'%'))
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status),
                fn ($q) => $q->whereIn('status', [LpnStatus::EMPTY, LpnStatus::ACTIVE]))   // default: yang masih dipakai
            ->when($request->filled('lpn_type'), fn ($q) => $q->where('lpn_type', $request->lpn_type))
            ->when($request->filled('printed'), fn ($q) => $request->printed === 'never'
                ? $q->where('print_count', 0) : $q->where('print_count', '>', 0))
            ->latest('id');
    }

    private function locationQuery(Request $request): Builder
    {
        return Location::query()
            ->with(['warehouse', 'zone', 'locationClass.locationType'])
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->q.'%'))
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone_id', $request->integer('zone')))
            ->when($request->filled('aisle'), fn ($q) => $q->where('aisle', $request->aisle))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->integer('level')))
            ->when($request->filled('function'), fn ($q) => $q->whereHas('locationClass.locationType',
                fn ($t) => $t->where('location_function', $request->function)))
            ->when($request->filled('printed'), fn ($q) => $request->printed === 'never'
                ? $q->where('print_count', 0) : $q->where('print_count', '>', 0))
            ->orderBy('warehouse_id')->orderByRaw('pick_sequence IS NULL, pick_sequence')->orderBy('code');
    }

    /** Pilihan filter untuk tampilan. */
    public static function functionOptions(): array
    {
        return collect(LocationFunction::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all();
    }
}
