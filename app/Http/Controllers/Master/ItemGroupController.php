<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemGroupController extends Controller
{
    public function index(Request $request): View
    {
        $groups = ItemGroup::query()
            ->withCount('itemClasses')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.item-groups.index', ['groups' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $group = ItemGroup::create($this->validated($request));

        return $this->ok("Item group {$group->code} dibuat.");
    }

    public function update(Request $request, ItemGroup $itemGroup): JsonResponse
    {
        $itemGroup->update($this->validated($request, $itemGroup));

        return $this->ok("Item group {$itemGroup->code} diperbarui.");
    }

    private function validated(Request $request, ?ItemGroup $group = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_item_group', 'code')->ignore($group)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
