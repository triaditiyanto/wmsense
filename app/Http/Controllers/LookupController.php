<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint JSON untuk dropdown pencarian (Tom Select) di dalam modal. */
class LookupController extends Controller
{
    public function items(Request $request): JsonResponse
    {
        $request->validate([
            'owner_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $q = trim((string) $request->input('q'));

        $items = Item::query()
            ->with(['baseUom', 'uoms.uom'])
            ->where('owner_id', $request->integer('owner_id'))
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('sku', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('barcode', $q);
            }))
            ->orderBy('sku')
            ->limit(30)
            ->get();

        return response()->json($items->map(fn (Item $item) => [
            'id' => $item->id,
            'text' => "{$item->sku} - {$item->name}",
            'is_lot_tracked' => $item->is_lot_tracked,
            'is_expiry_tracked' => $item->is_expiry_tracked,
            'uoms' => $item->uomOptions(),
        ]));
    }

    /**
     * Supplier aktif untuk dropdown. Dipakai inbound (receipt) & nanti outbound (retur ke supplier).
     * owner_id (opsional): hanya supplier milik owner tsb + supplier bersama.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $request->validate(['owner_id' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100']]);

        $rows = Supplier::query()
            ->where('is_active', true)
            ->when($request->filled('owner_id'), fn ($q) => $q->forOwner($request->integer('owner_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->limit(30)->get();

        return response()->json($rows->map(fn (Supplier $s) => [
            'id' => $s->id,
            'text' => "{$s->name} ({$s->code})",
            'city' => $s->city,
        ]));
    }

    /**
     * Customer aktif untuk dropdown. Dipakai inbound (retur customer) & outbound (order).
     * Ikut dikirim daftar alamat kirim aktif, untuk dipilih di dokumen outbound.
     */
    public function customers(Request $request): JsonResponse
    {
        $request->validate(['owner_id' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100']]);

        $rows = Customer::query()
            ->with(['addresses' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->when($request->filled('owner_id'), fn ($q) => $q->forOwner($request->integer('owner_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->limit(30)->get();

        return response()->json($rows->map(fn (Customer $c) => [
            'id' => $c->id,
            'text' => "{$c->name} ({$c->code})",
            'city' => $c->city,
            'addresses' => $c->addresses->map(fn ($a) => [
                'id' => $a->id, 'label' => $a->label, 'address' => $a->fullAddress(), 'is_default' => $a->is_default,
            ])->values(),
        ]));
    }
}
