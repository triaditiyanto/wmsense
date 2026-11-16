<?php

namespace App\Http\Controllers\Master;

use App\Enums\LpnStatus;
use App\Enums\LpnType;
use App\Http\Controllers\Controller;
use App\Models\Lpn;
use App\Models\Pallet;
use App\Models\Warehouse;
use App\Services\LpnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LpnController extends Controller
{
    public function __construct(private LpnService $service)
    {
    }

    public function index(Request $request): View
    {
        $lpns = Lpn::query()
            ->with(['warehouse', 'pallet', 'location', 'registeredBy'])
            ->when($request->filled('q'), fn ($q) => $q->where('lpn_no', 'like', '%'.strtoupper($request->q).'%'))
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('type'), fn ($q) => $q->where('lpn_type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('via'), fn ($q) => $q->where('registered_via', $request->via))
            ->latest('id')
            ->paginate(50)->withQueryString();

        return view('master.lpns.index', [
            'lpns' => $lpns,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(),
            'pallets' => Pallet::where('is_active', true)->orderBy('code')->get(),
            'types' => LpnType::cases(),
            'statuses' => LpnStatus::cases(),
            'maxGenerate' => LpnService::MAX_GENERATE,
        ]);
    }

    /** Registrasi satu LPN dengan nomor yang sudah ada (label pre-printed). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lpn_no' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.]+$/',
                Rule::unique('master_lpn', 'lpn_no')],
            'warehouse_id' => ['required', 'exists:master_warehouse,id'],
            ...$this->attributeRules(),
        ], ['lpn_no.unique' => 'Nomor LPN sudah terdaftar.']);
        $data['lpn_no'] = strtoupper($data['lpn_no']);
        $lpn = $this->service->register($data, $request->user());

        return $this->ok("LPN {$lpn->lpn_no} terdaftar.");
    }

    /** Generate banyak LPN dengan nomor otomatis. */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:master_warehouse,id'],
            'count' => ['required', 'integer', 'min:1', 'max:'.LpnService::MAX_GENERATE],
            ...$this->attributeRules(),
        ]);
        $lpns = $this->service->generate(
            Warehouse::findOrFail($data['warehouse_id']),
            LpnType::from($data['lpn_type']),
            $data['pallet_id'] ?? null,
            (int) $data['count'],
            $request->user(),
            $data['notes'] ?? null,
        );

        return $this->ok("{$lpns->count()} LPN dibuat: {$lpns->first()->lpn_no} s/d {$lpns->last()->lpn_no}.");
    }

    public function update(Request $request, Lpn $lpn): JsonResponse
    {
        $this->service->update($lpn, $request->validate($this->attributeRules()));

        return $this->ok("LPN {$lpn->lpn_no} diperbarui.");
    }

    public function void(Lpn $lpn): JsonResponse
    {
        $this->service->void($lpn);

        return $this->ok("LPN {$lpn->lpn_no} di-void.");
    }

    private function attributeRules(): array
    {
        return [
            'lpn_type' => ['required', Rule::enum(LpnType::class)],
            'pallet_id' => ['nullable', 'exists:master_pallet,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
