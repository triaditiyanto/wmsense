<?php

namespace App\Http\Controllers\Inbound;

use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Models\TaskHeader;
use App\Models\Warehouse;
use App\Services\PutawayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Putaway desktop (Inbound > Putaway) + endpoint JSON yang dipakai juga oleh layar mobile /m/putaway.
 * Semua aturan bisnis ada di PutawayService.
 */
class PutawayController extends Controller
{
    public function __construct(private PutawayService $service) {}

    public function index(Request $request): View
    {
        $tab = $request->input('tab') === 'history' ? 'history' : 'pending';

        return view('inbound.putaway.index', [
            'tab' => $tab,
            'pending' => $this->service->pending($request->integer('warehouse') ?: null, $request->input('q')),
            'history' => $tab === 'history' ? TaskHeader::query()
                ->where('task_type', TaskType::PUTAWAY)
                ->with(['lpn', 'fromLocation', 'suggestedLocation', 'toLocation', 'completer', 'creator'])
                ->withSum('details as qty_sum', 'qty_base')
                ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('task_no', 'like', "%{$request->q}%")
                    ->orWhereHas('lpn', fn ($l) => $l->where('lpn_no', 'like', "%{$request->q}%"))))
                ->latest('id')->paginate(30)->withQueryString() : null,
            'warehouses' => Warehouse::orderBy('code')->get(['id', 'code']),
        ]);
    }

    /** Scan LPN / klik Putaway -> task (baru atau yang masih berjalan). */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['lpn_no' => ['required', 'string', 'max:50']], [], ['lpn_no' => 'LPN']);
        $task = $this->service->start($data['lpn_no'], $request->user());

        return response()->json($this->service->payload($task));
    }

    public function show(TaskHeader $task): JsonResponse
    {
        return response()->json($this->service->payload($task));
    }

    /** Lokasi tujuan hasil scan (validasi awal sebelum simpan). */
    public function resolveLocation(Request $request, TaskHeader $task): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']]);
        $loc = $this->service->resolveLocation($task, $request->code);

        return response()->json(['id' => $loc->id, 'code' => $loc->code, 'zone' => $loc->zone->code, 'suggested' => $loc->id === $task->suggested_location_id]);
    }

    public function complete(Request $request, TaskHeader $task): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'confirm' => ['nullable', 'boolean'],
            'source' => ['nullable', 'in:DESKTOP,MOBILE'],
        ]);
        $source = $data['source'] ?? TaskHeader::SOURCE_DESKTOP;
        $task = $this->service->complete($task, $data['location_id'], $request->user(), $source, (bool) ($data['confirm'] ?? false));
        $task->load(['lpn', 'toLocation']);

        $message = "LPN {$task->lpn->lpn_no} disimpan di {$task->toLocation->code}.";
        if ($source === TaskHeader::SOURCE_DESKTOP) {
            session()->flash('success', $message);
        }

        return response()->json(['message' => $message, 'task_no' => $task->task_no, 'to' => $task->toLocation->code, 'lpn_no' => $task->lpn->lpn_no]);
    }

    public function cancel(Request $request, TaskHeader $task): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->service->cancel($task, $request->user(), $data['reason'] ?? null);

        return response()->json(['message' => "Task {$task->task_no} dibatalkan."]);
    }
}
