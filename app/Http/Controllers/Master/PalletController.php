<?php

namespace App\Http\Controllers\Master;

use App\Enums\PalletMaterial;
use App\Http\Controllers\Controller;
use App\Models\Pallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Jenis / spesifikasi pallet. */
class PalletController extends Controller
{
    public function index(Request $request): View
    {
        $pallets = Pallet::query()
            ->withCount('lpns')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.pallets.index', ['pallets' => $pallets, 'materials' => PalletMaterial::cases()]);
    }

    public function store(Request $request): JsonResponse
    {
        $pallet = Pallet::create($this->validated($request));

        return $this->ok("Jenis pallet {$pallet->code} dibuat.");
    }

    public function update(Request $request, Pallet $pallet): JsonResponse
    {
        $pallet->update($this->validated($request, $pallet));

        return $this->ok("Jenis pallet {$pallet->code} diperbarui.");
    }

    private function validated(Request $request, ?Pallet $pallet = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_pallet', 'code')->ignore($pallet)],
            'name' => ['required', 'string', 'max:255'],
            'material' => ['required', Rule::enum(PalletMaterial::class)],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'tare_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_load_kg' => ['nullable', 'numeric', 'min:0'],
            'max_stack_height_cm' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
