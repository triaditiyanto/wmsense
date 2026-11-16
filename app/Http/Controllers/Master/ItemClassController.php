<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemClass;
use App\Models\ItemGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemClassController extends Controller
{
    public function index(Request $request): View
    {
        $classes = ItemClass::query()
            ->with('itemGroup')
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('group'), fn ($q) => $q->where('item_group_id', $request->integer('group')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.item-classes.index', [
            'classes' => $classes,
            'groups' => ItemGroup::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $class = ItemClass::create($this->validated($request));

        return $this->ok("Item class {$class->code} dibuat.");
    }

    public function update(Request $request, ItemClass $itemClass): JsonResponse
    {
        $itemClass->update($this->validated($request, $itemClass));

        return $this->ok("Item class {$itemClass->code} diperbarui.");
    }

    private function validated(Request $request, ?ItemClass $class = null): array
    {
        $data = $request->validate([
            'item_group_id' => ['required', 'exists:master_item_group,id'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_item_class', 'code')->ignore($class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
