<?php

namespace App\Http\Controllers\Master;

use App\Enums\RotationRule;
use App\Enums\TemperatureClass;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemClass;
use App\Models\ItemUom;
use App\Models\Owner;
use App\Models\Uom;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Item (header) + UOM & konversi (detail). */
class ItemController extends Controller
{
    public function __construct(private ItemService $service)
    {
    }

    public function index(Request $request): View
    {
        $items = Item::query()
            ->with(['owner', 'itemClass.itemGroup', 'baseUom'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('sku', 'like', '%'.$request->q.'%')
                ->orWhere('name', 'like', '%'.$request->q.'%')
                ->orWhere('barcode', $request->q)))
            ->when($request->filled('owner'), fn ($q) => $q->where('owner_id', $request->integer('owner')))
            ->when($request->filled('class'), fn ($q) => $q->where('item_class_id', $request->integer('class')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('sku')
            ->paginate(50)->withQueryString();

        return view('master.items.index', ['items' => $items, ...$this->formOptions()]);
    }

    public function store(Request $request): JsonResponse
    {
        $item = $this->service->create($this->validated($request));

        return $this->ok("Item {$item->sku} dibuat. Silakan lengkapi UOM & konversi.", route('master.items.show', $item));
    }

    public function show(Item $item): View
    {
        $item->load(['owner', 'itemClass.itemGroup', 'baseUom', 'uoms' => fn ($q) => $q->with('uom')->orderBy('conversion_factor')]);

        return view('master.items.show', [
            'item' => $item,
            'isUsed' => $this->service->isUsed($item),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $this->service->update($item, $this->validated($request, $item));

        return $this->ok("Item {$item->sku} diperbarui.");
    }

    // ---------------------------------------------------------------- UOM (detail)

    public function storeUom(Request $request, Item $item): JsonResponse
    {
        $this->service->addUom($item, $this->validatedUom($request));

        return $this->ok('UOM ditambahkan.');
    }

    public function updateUom(Request $request, Item $item, ItemUom $uom): JsonResponse
    {
        $this->service->updateUom($uom, $this->validatedUom($request));

        return $this->ok('UOM diperbarui.');
    }

    public function destroyUom(Item $item, ItemUom $uom): JsonResponse
    {
        $this->service->deleteUom($uom);

        return $this->ok('UOM dihapus.');
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'owner_id' => ['required', 'exists:master_owner,id'],
            'item_class_id' => ['required', 'exists:master_item_class,id'],
            'base_uom_id' => ['required', 'exists:master_uom,id'],
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.\/]+$/',
                Rule::unique('master_item', 'sku')->where('owner_id', $request->integer('owner_id'))->ignore($item)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'rotation_rule' => ['required', Rule::enum(RotationRule::class)],
            'temperature_class' => ['required', Rule::enum(TemperatureClass::class)],
            'is_lot_tracked' => ['boolean'],
            'is_expiry_tracked' => ['boolean'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1'],
            'min_shelf_life_days' => ['nullable', 'integer', 'min:0', 'lte:shelf_life_days'],
            'is_hazardous' => ['boolean'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ], ['sku.unique' => 'SKU sudah dipakai untuk owner ini.']);
        $data['sku'] = strtoupper($data['sku']);
        foreach (['is_lot_tracked', 'is_expiry_tracked', 'is_hazardous', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        if ($data['rotation_rule'] === RotationRule::FEFO->value && ! $data['is_expiry_tracked']) {
            $data['is_expiry_tracked'] = true; // FEFO butuh tanggal expired
        }

        return $data;
    }

    private function validatedUom(Request $request): array
    {
        return $request->validate([
            'uom_id' => ['required', 'exists:master_uom,id'],
            'conversion_factor' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'is_default_inbound' => ['boolean'],
            'is_default_outbound' => ['boolean'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'owners' => Owner::where('is_active', true)->orderBy('code')->get(),
            'classes' => ItemClass::with('itemGroup')->where('is_active', true)->orderBy('code')->get(),
            'uoms' => Uom::orderBy('code')->get(),
            'rotations' => RotationRule::cases(),
            'temperatures' => TemperatureClass::cases(),
        ];
    }
}
