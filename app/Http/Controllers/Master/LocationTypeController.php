<?php

namespace App\Http\Controllers\Master;

use App\Enums\LocationFunction;
use App\Http\Controllers\Controller;
use App\Models\LocationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationTypeController extends Controller
{
    public function index(Request $request): View
    {
        $types = LocationType::query()
            ->withCount('locationClasses')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('function'), fn ($q) => $q->where('location_function', $request->function))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.location-types.index', ['types' => $types, 'functions' => LocationFunction::cases()]);
    }

    public function store(Request $request): JsonResponse
    {
        $type = LocationType::create($this->validated($request));

        return $this->ok("Tipe lokasi {$type->code} dibuat.");
    }

    public function update(Request $request, LocationType $locationType): JsonResponse
    {
        $locationType->update($this->validated($request, $locationType));

        return $this->ok("Tipe lokasi {$locationType->code} diperbarui.");
    }

    private function validated(Request $request, ?LocationType $type = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_location_type', 'code')->ignore($type)],
            'name' => ['required', 'string', 'max:255'],
            'location_function' => ['required', Rule::enum(LocationFunction::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
