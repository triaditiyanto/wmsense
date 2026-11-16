<?php

namespace App\Http\Controllers\Master;

use App\Enums\StorageType;
use App\Http\Controllers\Controller;
use App\Models\LocationClass;
use App\Models\LocationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationClassController extends Controller
{
    public function index(Request $request): View
    {
        $classes = LocationClass::query()
            ->with('locationType')
            ->withCount('locations')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('location_type_id', $request->integer('type')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.location-classes.index', [
            'classes' => $classes,
            'types' => LocationType::orderBy('code')->get(),
            'storageTypes' => StorageType::cases(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $class = LocationClass::create($this->validated($request));

        return $this->ok("Kelas lokasi {$class->code} dibuat.");
    }

    public function update(Request $request, LocationClass $locationClass): JsonResponse
    {
        $locationClass->update($this->validated($request, $locationClass));

        return $this->ok("Kelas lokasi {$locationClass->code} diperbarui.");
    }

    private function validated(Request $request, ?LocationClass $class = null): array
    {
        $data = $request->validate([
            'location_type_id' => ['required', 'exists:master_location_type,id'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_location_class', 'code')->ignore($class)],
            'name' => ['required', 'string', 'max:255'],
            'storage_type' => ['nullable', Rule::enum(StorageType::class)],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_volume_m3' => ['nullable', 'numeric', 'min:0'],
            'max_lpn' => ['nullable', 'integer', 'min:0', 'max:999'],
            'allow_mixed_items' => ['boolean'],
            'allow_mixed_lots' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        foreach (['allow_mixed_items', 'allow_mixed_lots', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        // Volume otomatis dari dimensi bila tidak diisi
        if (empty($data['max_volume_m3']) && ! empty($data['length_cm']) && ! empty($data['width_cm']) && ! empty($data['height_cm'])) {
            $data['max_volume_m3'] = round($data['length_cm'] * $data['width_cm'] * $data['height_cm'] / 1_000_000, 6);
        }

        return $data;
    }
}
