<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerController extends Controller
{
    public function index(Request $request): View
    {
        $owners = Owner::query()
            ->withCount('items')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')
            ->paginate(30)->withQueryString();

        return view('master.owners.index', ['owners' => $owners]);
    }

    public function store(Request $request): JsonResponse
    {
        $owner = Owner::create($this->validated($request));

        return $this->ok("Owner {$owner->name} dibuat.");
    }

    public function update(Request $request, Owner $owner): JsonResponse
    {
        $owner->update($this->validated($request, $owner));

        return $this->ok("Owner {$owner->name} diperbarui.");
    }

    private function validated(Request $request, ?Owner $owner = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('master_owner', 'code')->ignore($owner)],
            'name' => ['required', 'string', 'max:255'],
            'contact.pic' => ['nullable', 'string', 'max:100'],
            'contact.phone' => ['nullable', 'string', 'max:30'],
            'contact.email' => ['nullable', 'email', 'max:100'],
            'contact.address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], [], [
            'contact.pic' => 'PIC', 'contact.phone' => 'telepon', 'contact.email' => 'email', 'contact.address' => 'alamat',
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['contact'] = array_filter($data['contact'] ?? []) ?: null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
