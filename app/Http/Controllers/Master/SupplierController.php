<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->with('owner')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')
                ->orWhere('name', 'like', '%'.$request->q.'%')
                ->orWhere('city', 'like', '%'.$request->q.'%')))
            ->when($request->input('owner') === 'shared', fn ($q) => $q->whereNull('owner_id'))
            ->when(ctype_digit((string) $request->input('owner')), fn ($q) => $q->where('owner_id', $request->integer('owner')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.suppliers.index', [
            'suppliers' => $suppliers,
            'owners' => Owner::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = Supplier::create($this->validated($request));

        return $this->ok("Supplier {$supplier->name} dibuat.");
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return $this->ok("Supplier {$supplier->name} diperbarui.");
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_supplier', 'code')->ignore($supplier)],
            'name' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:master_owner,id'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'pic' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ], [], ['tax_id' => 'NPWP', 'pic' => 'PIC', 'phone' => 'telepon', 'city' => 'kota']);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
