<?php

namespace App\Http\Controllers\Master;

use App\Enums\LocationStatus;
use App\Enums\TemperatureClass;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationClass;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\LocationCapacityService;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $locations = Location::query()
            ->with(['warehouse', 'zone', 'locationClass.locationType'])
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->q.'%'))
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone_id', $request->integer('zone')))
            ->when($request->filled('class'), fn ($q) => $q->where('location_class_id', $request->integer('class')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('warehouse_id')->orderBy('code')
            ->paginate(50)->withQueryString();

        $zones = Zone::with('warehouse')->where('is_active', true)->orderBy('warehouse_id')->orderBy('code')->get();
        $classes = LocationClass::with('locationType')->where('is_active', true)->orderBy('code')->get();

        return view('master.locations.index', [
            'locations' => $locations,
            // pemakaian kapasitas untuk lokasi di halaman ini (1 query agregat)
            'capacity' => app(LocationCapacityService::class)->statusFor($locations->getCollection(), 0),
            'warehouses' => Warehouse::orderBy('code')->get(),
            'zones' => $zones,
            'classes' => $classes,
            // untuk <optgroup> di dropdown modal
            'zoneOptions' => $zones->groupBy(fn ($z) => $z->warehouse->code.' - '.$z->warehouse->name),
            'classOptions' => $classes->groupBy(fn ($c) => $c->locationType->name),
            'statuses' => LocationStatus::cases(),
            'temperatures' => TemperatureClass::cases(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $location = Location::create($this->validated($request));

        return $this->ok("Lokasi {$location->code} dibuat.");
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $location->update($this->validated($request, $location));

        return $this->ok("Lokasi {$location->code} diperbarui.");
    }

    public function generate(Request $request, LocationService $service): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:master_zone,id'],
            'location_class_id' => ['required', 'exists:master_location_class,id'],
            'aisles' => ['required', 'string', 'max:100'],
            'bay_from' => ['required', 'integer', 'min:1', 'max:999'],
            'bay_to' => ['required', 'integer', 'gte:bay_from', 'max:999'],
            'level_from' => ['required', 'integer', 'min:1', 'max:99'],
            'level_to' => ['required', 'integer', 'gte:level_from', 'max:99'],
        ]);
        $result = $service->generate($data);

        $msg = "{$result['created']} lokasi dibuat";
        if ($result['created']) {
            $msg .= " ({$result['first']} s/d {$result['last']})";
        }
        if ($result['skipped']) {
            $msg .= ", {$result['skipped']} dilewati karena sudah ada";
        }

        return $this->ok($msg.'.');
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        $zone = Zone::find($request->integer('zone_id'));
        $data = $request->validate([
            'zone_id' => ['required', 'exists:master_zone,id'],
            'location_class_id' => ['required', 'exists:master_location_class,id'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_.]+$/',
                Rule::unique('master_location', 'code')->where('warehouse_id', $zone?->warehouse_id)->ignore($location)],
            'status' => ['required', Rule::enum(LocationStatus::class)],
            'aisle' => ['nullable', 'string', 'max:10'],
            'bay' => ['nullable', 'string', 'max:10'],
            'level' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position' => ['nullable', 'string', 'max:10'],
            'pick_sequence' => ['nullable', 'integer', 'min:0'],
            'temperature_class' => ['nullable', Rule::enum(TemperatureClass::class)],
            // kosong = ikut kelas lokasi
            'max_lpn' => ['nullable', 'integer', 'min:0', 'max:999'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_volume_m3' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['warehouse_id'] = $zone->warehouse_id; // gudang selalu mengikuti zona

        if ($location && $location->warehouse_id !== $data['warehouse_id'] && $location->inventory()->exists()) {
            abort(422, 'Lokasi berisi stok tidak bisa dipindah ke gudang lain.');
        }

        return $data;
    }
}
