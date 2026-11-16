<?php

namespace App\Http\Controllers\Master;

use App\Enums\TemperatureClass;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Warehouse (header) + Zona (detail). */
class WarehouseController extends Controller
{
    public const TIMEZONES = ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'];

    public function index(Request $request): View
    {
        $warehouses = Warehouse::query()
            ->withCount(['zones', 'locations'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.warehouses.index', ['warehouses' => $warehouses, 'timezones' => self::TIMEZONES]);
    }

    public function store(Request $request): JsonResponse
    {
        $warehouse = Warehouse::create($this->validated($request));

        return $this->ok("Gudang {$warehouse->code} dibuat. Silakan tambahkan zona.", route('master.warehouses.show', $warehouse));
    }

    public function show(Warehouse $warehouse): View
    {
        $warehouse->load(['zones' => fn ($q) => $q->withCount('locations')->orderBy('code')]);

        return view('master.warehouses.show', [
            'warehouse' => $warehouse,
            'timezones' => self::TIMEZONES,
            'temperatures' => TemperatureClass::cases(),
        ]);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($this->validated($request, $warehouse));

        return $this->ok("Gudang {$warehouse->code} diperbarui.");
    }

    // ---------------------------------------------------------------- zona (detail)

    public function storeZone(Request $request, Warehouse $warehouse): JsonResponse
    {
        $zone = $warehouse->zones()->create($this->validatedZone($request, $warehouse));

        return $this->ok("Zona {$zone->code} ditambahkan.");
    }

    public function updateZone(Request $request, Warehouse $warehouse, Zone $zone): JsonResponse
    {
        $zone->update($this->validatedZone($request, $warehouse, $zone));

        return $this->ok("Zona {$zone->code} diperbarui.");
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $data = $request->validate([
            // kode dipakai di nomor dokumen (RCV-WH1-...), jadi tidak bisa diubah setelah dibuat
            'code' => $warehouse ? ['nullable'] : ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_warehouse', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'timezone' => ['required', Rule::in(array_keys(self::TIMEZONES))],
            'is_active' => ['boolean'],
        ]);
        if ($warehouse) {
            unset($data['code']); // diabaikan saat ubah
        } else {
            $data['code'] = strtoupper($data['code']);
        }
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function validatedZone(Request $request, Warehouse $warehouse, ?Zone $zone = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('master_zone', 'code')->where('warehouse_id', $warehouse->id)->ignore($zone)],
            'name' => ['required', 'string', 'max:255'],
            'temperature_class' => ['required', Rule::enum(TemperatureClass::class)],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
