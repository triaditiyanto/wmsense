<?php

namespace App\Http\Controllers\Master;

use App\Enums\LocatingScope;
use App\Enums\LocatingTarget;
use App\Enums\LocationFunction;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\LocatingRule;
use App\Models\Location;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\LocatingRuleService;
use App\Services\LocatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master > Item Locating: editor drag & drop aturan locating putaway per Item / Item Class / Item Group.
 *   index    : halaman (public/js/locating-rules.js)
 *   data     : pohon barang + aturan + area gudang (zona, aisle, kelas lokasi, lokasi STORAGE) untuk satu gudang
 *   save     : simpan aturan satu cakupan (ganti semua target)
 *   destroy  : hapus aturan -> cakupan kembali ikut aturan induknya
 *   simulate : saran lokasi untuk satu item memakai aturan draft (belum disimpan) atau aturan tersimpan
 */
class LocatingRuleController extends Controller
{
    public function __construct(private LocatingRuleService $rules) {}

    public function index(Request $request): View
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('code')->get();

        return view('master.locating.index', [
            'warehouses' => $warehouses,
            'warehouse' => $warehouses->firstWhere('id', $request->integer('warehouse')) ?? $warehouses->first(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $wh = Warehouse::findOrFail($request->integer('warehouse'));

        $groups = ItemGroup::with(['itemClasses' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'itemClasses.items' => fn ($q) => $q->where('is_active', true)->with('owner')->orderBy('sku')])
            ->where('is_active', true)->orderBy('name')->get();

        $rules = LocatingRule::with(['targets.zone', 'targets.locationClass', 'targets.location', 'updatedBy'])
            ->where('warehouse_id', $wh->id)->get();

        $locations = Location::with(['locationClass.locationType', 'zone'])
            ->where('warehouse_id', $wh->id)
            ->whereHas('locationClass.locationType', fn ($q) => $q->where('location_function', LocationFunction::STORAGE))
            ->orderByRaw('pick_sequence IS NULL, pick_sequence')->orderBy('code')->get();
        $used = DB::table('o_inventory')->whereIn('location_id', $locations->pluck('id'))->where('qty_on_hand', '>', 0)
            ->distinct()->pluck('location_id')->flip();

        $zones = Zone::where('warehouse_id', $wh->id)->orderBy('code')->get();

        return response()->json([
            'warehouse' => ['id' => $wh->id, 'code' => $wh->code],
            'tree' => $groups->map(fn ($g) => [
                'id' => $g->id, 'code' => $g->code, 'name' => $g->name,
                'classes' => $g->itemClasses->map(fn ($c) => [
                    'id' => $c->id, 'code' => $c->code, 'name' => $c->name,
                    'items' => $c->items->map(fn ($i) => ['id' => $i->id, 'sku' => $i->sku, 'name' => $i->name, 'owner' => $i->owner->code, 'temp' => $i->temperature_class->value])->values(),
                ])->values(),
            ])->values(),
            'rules' => $rules->map(fn ($r) => $this->ruleJson($r))->values(),
            'zones' => $zones->map(fn ($z) => ['id' => $z->id, 'code' => $z->code, 'name' => $z->name, 'temp' => $z->temperature_class->value,
                'count' => $locations->where('zone_id', $z->id)->count()])->filter(fn ($z) => $z['count'] > 0)->values(),
            'aisles' => $locations->filter(fn ($l) => $l->aisle)->groupBy(fn ($l) => $l->zone_id.'|'.$l->aisle)->map(fn ($list) => [
                'zone_id' => $list->first()->zone_id, 'zone' => $list->first()->zone->code, 'aisle' => $list->first()->aisle,
                'bays' => $list->pluck('bay')->unique()->count(), 'levels' => [$list->min('level') ?? 1, $list->max('level') ?? 1],
                'count' => $list->count(), 'used' => $list->filter(fn ($l) => $used->has($l->id))->count(),
            ])->sortBy(fn ($a) => $a['zone'].'|'.$a['aisle'], SORT_NATURAL)->values(),
            'classes' => $locations->groupBy('location_class_id')->map(fn ($list) => [
                'id' => $list->first()->location_class_id, 'code' => $list->first()->locationClass->code, 'name' => $list->first()->locationClass->name,
                'count' => $list->count(),
            ])->values(),
            'locations' => $locations->map(fn ($l) => [
                'id' => $l->id, 'code' => $l->code, 'zone_id' => $l->zone_id, 'zone' => $l->zone->code, 'aisle' => $l->aisle,
                'bay' => $l->bay, 'level' => $l->level ?? 1, 'class_id' => $l->location_class_id, 'used' => $used->has($l->id),
                'active' => $l->status->value === 'ACTIVE',
            ])->values(),
            'scopes' => collect(LocatingScope::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
            'targets' => collect(LocatingTarget::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $rule = $this->rules->save($data, $request->user());
        $name = $this->rules->scopeName($rule->scope_type, $rule->scope_id);

        return response()->json([
            'message' => "Aturan locating {$name} disimpan (".$rule->targets->count().' area).',
            'rule' => $this->ruleJson($rule->load(['targets.zone', 'targets.locationClass', 'targets.location', 'updatedBy'])),
        ]);
    }

    public function destroy(LocatingRule $rule): JsonResponse
    {
        $name = $this->rules->scopeName($rule->scope_type, $rule->scope_id);
        $this->rules->delete($rule);

        return response()->json(['message' => "Aturan {$name} dihapus. Sekarang ikut aturan induknya (bila ada)."]);
    }

    public function simulate(Request $request, LocatingService $locating): JsonResponse
    {
        $request->validate(['item_id' => ['required', 'integer', 'exists:master_item,id'], 'draft' => ['nullable', 'array']]);
        $item = Item::with('itemClass')->findOrFail($request->integer('item_id'));
        $whId = $request->integer('warehouse_id');
        if (! Warehouse::whereKey($whId)->exists()) {
            throw new BusinessException('Gudang tidak valid.');
        }

        // draft dipakai bila cakupannya mengenai item ini dan lebih spesifik / sama dengan aturan tersimpan
        $rule = null;
        $source = 'none';
        if ($draft = $request->input('draft')) {
            $data = $this->validated(new Request([...$draft, 'warehouse_id' => $whId]));
            $covers = match ($data['scope_type']) {
                'ITEM' => $data['scope_id'] === $item->id,
                'ITEM_CLASS' => $data['scope_id'] === $item->item_class_id,
                'ITEM_GROUP' => $data['scope_id'] === $item->itemClass->item_group_id,
            };
            $saved = $this->rules->resolve($whId, $item->id);
            if ($covers && (! $saved || $saved->scope_type->specificity() <= LocatingScope::from($data['scope_type'])->specificity())) {
                $rule = $this->rules->draft($data);
                $source = 'draft';
            }
        }
        if (! $rule && ($rule = $this->rules->resolve($whId, $item->id))) {
            $source = 'saved';
        }

        $contents = collect([(object) ['item_id' => $item->id, 'lot_no' => null, 'qty_on_hand' => 1, 'item' => $item]]);
        $suggestions = $locating->suggest($whId, $contents, 6, null, $rule ?? false);

        return response()->json([
            'source' => $source,
            'rule_name' => $rule ? $this->rules->scopeName($rule->scope_type, (int) $rule->scope_id) : null,
            'suggestions' => $suggestions,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('master_warehouse', 'id')],
            'scope_type' => ['required', Rule::enum(LocatingScope::class)],
            'scope_id' => ['required', 'integer', 'min:1'],
            'level_preference' => ['nullable', Rule::in([LocatingRule::LEVEL_LOW, LocatingRule::LEVEL_HIGH])],
            'prefer_consolidate' => ['nullable', 'boolean'],
            'strict' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'targets' => ['present', 'array', 'max:30'],
            'targets.*.target_type' => ['required', Rule::enum(LocatingTarget::class)],
            'targets.*.zone_id' => ['nullable', 'integer'],
            'targets.*.aisle' => ['nullable', 'string', 'max:10'],
            'targets.*.location_class_id' => ['nullable', 'integer'],
            'targets.*.location_id' => ['nullable', 'integer'],
            'targets.*.level_min' => ['nullable', 'integer', 'min:1', 'max:99'],
            'targets.*.level_max' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $table = ['ITEM' => 'master_item', 'ITEM_CLASS' => 'master_item_class', 'ITEM_GROUP' => 'master_item_group'][$data['scope_type']];
        if (! DB::table($table)->where('id', $data['scope_id'])->exists()) {
            throw new BusinessException('Barang / kelompok tidak ditemukan.');
        }
        $data['scope_id'] = (int) $data['scope_id'];
        $data['warehouse_id'] = (int) $data['warehouse_id'];

        return $data;
    }

    private function ruleJson(LocatingRule $r): array
    {
        return [
            'id' => $r->id,
            'scope_type' => $r->scope_type->value,
            'scope_id' => (int) $r->scope_id,
            'level_preference' => $r->level_preference,
            'prefer_consolidate' => $r->prefer_consolidate,
            'strict' => $r->strict,
            'is_active' => $r->is_active,
            'notes' => $r->notes,
            'updated' => trim(($r->updatedBy?->name ?? '').' · '.$r->updated_at?->format('d/m/Y H:i'), ' ·'),
            'targets' => $r->targets->map(fn ($t) => [
                'target_type' => $t->target_type->value, 'zone_id' => $t->zone_id, 'aisle' => $t->aisle,
                'location_class_id' => $t->location_class_id, 'location_id' => $t->location_id,
                'level_min' => $t->level_min, 'level_max' => $t->level_max, 'label' => LocatingRuleService::targetLabel($t),
            ])->values(),
        ];
    }
}
