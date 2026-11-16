<?php

namespace App\Services;

use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\LpnStatus;
use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmationRequiredException;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Lpn;
use App\Models\ReceiptHeader;
use App\Models\ReceiptReceiving;
use App\Models\TaskHeader;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Putaway: pindahkan SATU LPN utuh dari dock (fungsi RECEIVING) ke lokasi simpan.
 *
 *   start()    : scan LPN / klik Putaway -> task IN_PROGRESS + lokasi saran (dipesan, lihat LocatingService)
 *   complete() : scan lokasi tujuan -> stok dipindah (PUTAWAY_OUT di dock, PUTAWAY_IN di tujuan, received_at dibawa),
 *                LPN pindah lokasi, baris terima ditandai putaway_at (hilang dari Intransit In),
 *                receipt RECEIVED yang semua barangnya sudah disimpan -> CLOSED.
 *   cancel()   : batalkan task (slot saran dilepas), stok tidak berubah.
 * Desktop & mobile memakai service ini lewat PutawayController (aturan bisnis identik).
 */
class PutawayService
{
    public function __construct(
        private InventoryService $inventory,
        private LocatingService $locating,
        private DocumentNumberService $numbers,
    ) {}

    /** Ringkasan LPN yang menunggu putaway (stok di lokasi dock). */
    public function pending(?int $warehouseId = null, ?string $q = null): Collection
    {
        $rows = DB::table('o_inventory as i')
            ->join('master_lpn as p', 'p.id', '=', 'i.lpn_id')
            ->join('master_location as l', 'l.id', '=', 'i.location_id')
            ->join('master_location_class as c', 'c.id', '=', 'l.location_class_id')
            ->join('master_location_type as t', 't.id', '=', 'c.location_type_id')
            ->join('master_item as it', 'it.id', '=', 'i.item_id')
            ->where('t.location_function', LocationFunction::RECEIVING->value)
            ->where('i.qty_on_hand', '>', 0)
            ->when($warehouseId, fn ($w) => $w->where('i.warehouse_id', $warehouseId))
            ->when($q, fn ($w) => $w->where(fn ($s) => $s->where('p.lpn_no', 'like', "%{$q}%")->orWhere('it.sku', 'like', "%{$q}%")->orWhere('l.code', 'like', "%{$q}%")))
            ->groupBy('i.lpn_id', 'p.lpn_no', 'i.location_id', 'l.code', 'i.warehouse_id')
            ->selectRaw('i.lpn_id, p.lpn_no, i.location_id, l.code as location_code, i.warehouse_id,
                COUNT(*) as line_count, SUM(i.qty_on_hand) as qty, MIN(i.received_at) as received_at,
                GROUP_CONCAT(DISTINCT it.sku ORDER BY it.sku SEPARATOR ", ") as skus')
            ->orderBy('received_at')
            ->get();

        $tasks = TaskHeader::open()->where('task_type', TaskType::PUTAWAY)
            ->whereIn('lpn_id', $rows->pluck('lpn_id'))->with(['suggestedLocation', 'creator'])->get()->keyBy('lpn_id');

        return $rows->map(function ($r) use ($tasks) {
            $r->task = $tasks[$r->lpn_id] ?? null;

            return $r;
        });
    }

    /** Mulai task untuk LPN (atau lanjutkan task yang masih jalan untuk LPN tsb). */
    public function start(string $lpnCode, User $user, ?int $warehouseId = null): TaskHeader
    {
        return DB::transaction(function () use ($lpnCode, $user, $warehouseId) {
            $no = strtoupper(trim($lpnCode));
            $lpn = Lpn::with('location.locationClass.locationType')->where('lpn_no', $no)->lockForUpdate()->first();
            if (! $lpn) {
                throw new BusinessException("LPN {$no} tidak terdaftar.");
            }
            if ($warehouseId && $lpn->warehouse_id !== $warehouseId) {
                throw new BusinessException("LPN {$no} milik gudang lain.");
            }
            if ($lpn->status !== LpnStatus::ACTIVE || ! $lpn->location) {
                throw new BusinessException("LPN {$no} kosong / tidak berisi stok.");
            }
            if ($lpn->location->locationFunction() !== LocationFunction::RECEIVING) {
                throw new BusinessException("LPN {$no} sudah berada di {$lpn->location->code}, bukan di dock (sudah disimpan).");
            }

            $open = TaskHeader::open()->where('task_type', TaskType::PUTAWAY)->where('lpn_id', $lpn->id)->lockForUpdate()->first();
            if ($open) {
                return $open;
            }

            $contents = $this->contents($lpn->id, $lpn->location_id);
            if ($contents->isEmpty()) {
                throw new BusinessException("LPN {$no} tidak berisi stok di {$lpn->location->code}.");
            }
            $best = $this->locating->suggest($lpn->warehouse_id, $contents, 1)[0] ?? null;

            return TaskHeader::create([
                'task_no' => $this->numbers->next(TaskType::PUTAWAY->prefix(), Warehouse::findOrFail($lpn->warehouse_id)),
                'task_type' => TaskType::PUTAWAY,
                'status' => TaskStatus::IN_PROGRESS,
                'warehouse_id' => $lpn->warehouse_id,
                'lpn_id' => $lpn->id,
                'from_location_id' => $lpn->location_id,
                'suggested_location_id' => $best['id'] ?? null,
                'suggest_reason' => $best['reason'] ?? 'Tidak ada lokasi yang cocok, pilih manual.',
                'created_by' => $user->id,
            ]);
        });
    }

    /** Data task untuk layar desktop/mobile: isi LPN, saran, alternatif. */
    public function payload(TaskHeader $task): array
    {
        $task->loadMissing(['lpn', 'fromLocation', 'suggestedLocation', 'toLocation']);
        $contents = $this->contents($task->lpn_id, $task->from_location_id);
        $alternatives = $task->status === TaskStatus::IN_PROGRESS
            ? $this->locating->suggest($task->warehouse_id, $contents, 6, $task->id) : [];

        return [
            'id' => $task->id,
            'task_no' => $task->task_no,
            'status' => $task->status->value,
            'lpn_no' => $task->lpn->lpn_no,
            'from' => $task->fromLocation->code,
            'suggested' => $task->suggestedLocation ? ['id' => $task->suggestedLocation->id, 'code' => $task->suggestedLocation->code, 'reason' => $task->suggest_reason] : null,
            'suggest_reason' => $task->suggest_reason,
            'to' => $task->toLocation?->code,
            'alternatives' => array_values(array_filter($alternatives, fn ($a) => $a['id'] !== $task->suggested_location_id)),
            'contents' => $contents->map(fn (Inventory $i) => [
                'sku' => $i->item->sku, 'name' => $i->item->name, 'lot_no' => $i->lot_no,
                'expiry_date' => $i->expiry_date?->format('d/m/Y'), 'status' => $i->status->label(),
                'qty' => Qty::fmt($i->qty_on_hand), 'uom' => $i->item->baseUom->code,
            ])->values(),
        ];
    }

    /** Lokasi tujuan hasil scan: harus satu gudang, aktif, fungsi STORAGE / PICK_FACE. */
    public function resolveLocation(TaskHeader $task, string $code): Location
    {
        $loc = Location::with(['zone', 'locationClass.locationType'])
            ->where('warehouse_id', $task->warehouse_id)->where('code', strtoupper(trim($code)))->first();
        if (! $loc) {
            throw new BusinessException("Lokasi {$code} tidak ditemukan di gudang ini.");
        }
        $this->assertDestination($loc, $task);

        return $loc;
    }

    /** Selesaikan putaway ke lokasi tujuan. $confirm = setujui peringatan (butuh hak INB_PUTAWAY:approve). */
    public function complete(TaskHeader $task, int $locationId, User $user, string $source, bool $confirm = false): TaskHeader
    {
        return DB::transaction(function () use ($task, $locationId, $user, $source, $confirm) {
            $task = TaskHeader::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($task->status !== TaskStatus::IN_PROGRESS) {
                throw new BusinessException("Task {$task->task_no} sudah {$task->status->label()}.");
            }
            $lpn = Lpn::whereKey($task->lpn_id)->lockForUpdate()->firstOrFail();
            if ($lpn->location_id !== $task->from_location_id) {
                throw new BusinessException("LPN {$lpn->lpn_no} sudah tidak di dock asal. Batalkan task ini.");
            }

            $to = Location::with(['zone', 'locationClass.locationType'])->findOrFail($locationId);
            $this->assertDestination($to, $task);

            $contents = $this->contents($lpn->id, $task->from_location_id, lock: true);
            if ($contents->isEmpty()) {
                throw new BusinessException("LPN {$lpn->lpn_no} sudah kosong (mungkin dibatalkan dari Intransit In). Batalkan task ini.");
            }

            $warnings = $this->locating->check($to, $contents, $task->id);
            if ($warnings) {
                $canConfirm = $user->hasAccess('INB_PUTAWAY', 'approve');
                if (! $confirm) {
                    throw new ConfirmationRequiredException($warnings, $canConfirm);
                }
                if (! $canConfirm) {
                    throw new BusinessException('Butuh persetujuan SPV: '.implode(' ', $warnings));
                }
            }

            // ---- pindah stok: keluar dari dock, masuk ke lokasi tujuan (received_at ikut, FIFO tetap benar)
            $seq = 0;
            foreach ($contents as $row) {
                $qty = $row->qty_on_hand;
                $receivedAt = $row->received_at;
                $this->inventory->changeOnHand(StockAttributes::fromInventory($row), Qty::sub(0, $qty), MovementType::PUTAWAY_OUT,
                    new MovementContext($task, $user->id, null, ++$seq, 'PUTAWAY'));
                $this->inventory->changeOnHand(
                    new StockAttributes($row->warehouse_id, $to->id, $row->item_id, $row->owner_id, $row->lot_no,
                        $row->expiry_date?->format('Y-m-d'), $row->lpn_id, $row->status),
                    $qty, MovementType::PUTAWAY_IN, new MovementContext($task, $user->id, null, ++$seq, 'PUTAWAY'), $receivedAt,
                );
                $task->details()->create([
                    'item_id' => $row->item_id, 'owner_id' => $row->owner_id, 'lot_no' => $row->lot_no,
                    'expiry_date' => $row->expiry_date, 'inventory_status' => $row->status, 'qty_base' => $qty, 'received_at' => $receivedAt,
                ]);
            }
            $lpn->update(['location_id' => $to->id]);

            // ---- baris terima -> sudah disimpan; receipt yang semua barangnya tersimpan -> CLOSED
            $lines = ReceiptReceiving::where('lpn_id', $lpn->id)->where('location_id', $task->from_location_id)
                ->whereNull('cancelled_at')->whereNull('putaway_at');
            $headerIds = (clone $lines)->pluck('receipt_header_id')->unique();
            $lines->update(['putaway_task_id' => $task->id, 'putaway_at' => now()]);
            foreach ($headerIds as $hid) {
                $this->closeIfStored(ReceiptHeader::whereKey($hid)->lockForUpdate()->first(), $user);
            }

            $task->update([
                'status' => TaskStatus::DONE, 'to_location_id' => $to->id, 'source' => $source,
                'completed_by' => $user->id, 'completed_at' => now(),
            ]);

            return $task;
        });
    }

    public function cancel(TaskHeader $task, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($task, $user, $reason) {
            $task = TaskHeader::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($task->status !== TaskStatus::IN_PROGRESS) {
                throw new BusinessException("Task {$task->task_no} sudah {$task->status->label()}.");
            }
            $task->update(['status' => TaskStatus::CANCELLED, 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        });
    }

    /** Receipt RECEIVED yang semua baris terimanya sudah disimpan -> CLOSED. Dipanggil juga saat Selesai Terima. */
    public function closeIfStored(?ReceiptHeader $header, User $user): bool
    {
        if (! $header || $header->status !== ReceiptStatus::RECEIVED) {
            return false;
        }
        $pending = $header->receivings()->whereNull('cancelled_at')->whereNull('putaway_at')->exists();
        if ($pending || ! $header->receivings()->whereNull('cancelled_at')->exists()) {
            return false;
        }
        $header->update(['status' => ReceiptStatus::CLOSED, 'updated_by' => $user->id]);

        return true;
    }

    /** @return Collection<int, Inventory> stok LPN di lokasi (dengan item & base UOM) */
    private function contents(int $lpnId, int $locationId, bool $lock = false): Collection
    {
        return Inventory::with('item.baseUom')
            ->where('lpn_id', $lpnId)->where('location_id', $locationId)->where('qty_on_hand', '>', 0)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->orderBy('id')->get();
    }

    private function assertDestination(Location $loc, TaskHeader $task): void
    {
        if ($loc->warehouse_id !== $task->warehouse_id) {
            throw new BusinessException("Lokasi {$loc->code} ada di gudang lain.");
        }
        if ($loc->status !== LocationStatus::ACTIVE) {
            throw new BusinessException("Lokasi {$loc->code} berstatus {$loc->status->label()}.");
        }
        if (! in_array($loc->locationFunction(), [LocationFunction::STORAGE, LocationFunction::PICK_FACE], true)) {
            throw new BusinessException("Lokasi {$loc->code} bukan lokasi simpan (fungsi {$loc->locationFunction()->value}).");
        }
        if ($loc->id === $task->from_location_id) {
            throw new BusinessException('Lokasi tujuan sama dengan lokasi asal.');
        }
    }
}
