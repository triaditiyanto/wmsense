<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UomController extends Controller
{
    public function index(Request $request): View
    {
        $uoms = Uom::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->orderBy('code')
            ->paginate(50)->withQueryString();

        return view('master.uoms.index', ['uoms' => $uoms]);
    }

    public function store(Request $request): JsonResponse
    {
        $uom = Uom::create($this->validated($request));

        return $this->ok("UOM {$uom->code} dibuat.");
    }

    public function update(Request $request, Uom $uom): JsonResponse
    {
        $uom->update($this->validated($request, $uom));

        return $this->ok("UOM {$uom->code} diperbarui.");
    }

    private function validated(Request $request, ?Uom $uom = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('master_uom', 'code')->ignore($uom)],
            'name' => ['required', 'string', 'max:255'],
            'allow_decimal' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['allow_decimal'] = $request->boolean('allow_decimal');

        return $data;
    }
}
