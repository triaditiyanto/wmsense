<?php

namespace App\Services;

use App\Enums\DiscrepancyType;
use App\Enums\LocationFunction;
use App\Enums\LocationStatus;
use App\Enums\LpnStatus;
use App\Enums\LpnType;
use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Enums\ReceivingCondition;
use App\Exceptions\BusinessException;
use App\Exceptions\ConfirmationRequiredException;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\Lpn;
use App\Models\ReceiptDetail;
use App\Models\ReceiptHeader;
use App\Models\ReceiptReceiving;
use App\Models\User;
use App\Services\Inventory\MovementContext;
use App\Services\Inventory\StockAttributes;
use App\Support\Qty;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use App\Notifications\DockFullNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Proses penerimaan barang (receiving) untuk desktop & mobile.
 * Urutan kunci (mencegah deadlock): receipt header -> detail -> LPN -> inventory.
 */
class ReceivingService
{
    public const RECEIVABLE = [ReceiptStatus::OPEN, ReceiptStatus::RECEIVING];

    public function __construct(
        private InventoryService $inventory,
        private LpnService $lpns,
        private LocationCapacityService $capacity,
    ) {
    }

    // =================================================================== DOCK (kapasitas & saran)

    /**
     * Status semua dock gudang dokumen + saran dock untuk LPN BARU:
     * dock saat ini selama masih muat -> bila penuh, dock paling kosong -> bila semua penuh: all_full.
     */
    public function dockStatus(ReceiptHeader $header, ?int $currentId = null): array
    {
        $docks = Location::with('locationClass')
            ->where('warehouse_id', $header->warehouse_id)
            ->where('status', LocationStatus::ACTIVE)
            ->whereHas('locationClass.locationType', fn ($q) => $q->where('location_function', LocationFunction::RECEIVING))
            ->orderBy('code')
            ->get();

        $status = $this->capacity->statusFor($docks);
        $current = $currentId ? ($status[$currentId] ?? null) : null;

        $suggested = ($current && ! $current['full'])
            ? $current
            : collect($status)->where('full', false)->sortBy([['used_lpn', 'asc'], ['percent', 'asc'], ['code', 'asc']])->first();

        return [
            'docks' => array_values($status),
            'suggested' => $suggested,
            'current_full' => (bool) ($current['full'] ?? false),
            'all_full' => $docks->isNotEmpty() && $suggested === null,
        ];
    }

    /**
     * Semua dock penuh: kirim notifikasi ke Admin ke atas (role level <= 3).
     * Dibatasi sekali per gudang per 10 menit (Cache::add hanya berhasil bila key belum ada).
     */
    public function notifyDockFull(ReceiptHeader $header, User $sender): int
    {
        $status = $this->dockStatus($header);
        if (! $status['all_full']) {
            throw new BusinessException('Masih ada dock yang muat: '.($status['suggested']['code'] ?? '-').'. Notifikasi tidak dikirim.');
        }
        if (! Cache::add("notify:dock-full:{$header->warehouse_id}", now()->timestamp, now()->addMinutes(10))) {
            throw new BusinessException('Notifikasi dock penuh sudah dikirim kurang dari 10 menit lalu. Mohon tunggu admin.');
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->whereKeyNot($sender->id)
            ->whereHas('role', fn ($q) => $q->where('is_active', true)->where('level', '<=', 3))
            ->get();

        Notification::send($recipients, new DockFullNotification($header->loadMissing('warehouse'), $sender, $status['docks']));

        return $recipients->count();
    }

    // =================================================================== TERIMA

    /**
     * $data: location_id, lpn_no (kosong = buat LPN baru), item_id, uom_id, qty, lot_no, expiry_date,
     *        condition (GOOD|DAMAGED), notes, idempotency_key, confirm (1 = setujui peringatan)
     */
    public function receive(ReceiptHeader $receipt, array $data, User $user, string $source): ReceiptReceiving
    {
        $key = $data['idempotency_key'] ?? null;
        if ($key && $existing = ReceiptReceiving::where('idempotency_key', $key)->first()) {
            return $existing; // dikirim ulang (mis. sinyal putus) -> jangan dicatat dobel
        }

        try {
            return DB::transaction(fn () => $this->doReceive($receipt, $data, $user, $source));
        } catch (UniqueConstraintViolationException $e) {
            // Dua request identik bersamaan: yang kalah cukup mengembalikan hasil yang menang
            if ($key && $existing = ReceiptReceiving::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            throw $e;
        }
    }

    private function doReceive(ReceiptHeader $receipt, array $data, User $user, string $source): ReceiptReceiving
    {
        $header = ReceiptHeader::with('warehouse')->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
        $this->assertReceivable($header);

        $location = $this->receivingLocation($header, (int) $data['location_id']);
        $item = Item::with(['baseUom', 'uoms'])->findOrFail($data['item_id']);

        $lines = $header->details()->where('item_id', $item->id)->reorder('line_no')->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw new BusinessException("Item {$item->sku} tidak ada di dokumen {$header->receipt_no}.");
        }

        // ---- qty & UOM
        $factor = $item->conversionFactor((int) $data['uom_id']);
        if ($factor === null) {
            throw new BusinessException("UOM tidak terdaftar untuk item {$item->sku}.");
        }
        $qtyBase = Qty::mul($data['qty'], $factor);
        if (Qty::cmp($qtyBase, 0) <= 0) {
            throw new BusinessException('Qty harus lebih dari 0.');
        }
        if (! $item->baseUom->allow_decimal && ! Qty::isWhole($qtyBase)) {
            throw new BusinessException("Qty dalam {$item->baseUom->code} harus bilangan bulat (hasil: ".Qty::fmt($qtyBase).').');
        }

        // ---- lot & expired
        $lot = isset($data['lot_no']) && trim($data['lot_no']) !== '' ? strtoupper(trim($data['lot_no'])) : null;
        $exp = ! empty($data['expiry_date']) ? Carbon::parse($data['expiry_date'])->startOfDay() : null;
        if ($item->is_lot_tracked && ! $lot) {
            throw new BusinessException("Lot wajib diisi untuk item {$item->sku}.");
        }
        if ($item->is_expiry_tracked && ! $exp) {
            throw new BusinessException("Tanggal expired wajib diisi untuk item {$item->sku}.");
        }
        if ($exp && $exp->lt(today())) {
            throw new BusinessException('Barang sudah kedaluwarsa ('.$exp->format('d/m/Y').'), tidak bisa diterima.');
        }

        // ---- peringatan (butuh konfirmasi SPV)
        $warnings = [];
        if ($exp && $item->min_shelf_life_days) {
            $left = (int) today()->diffInDays($exp);
            if ($left < $item->min_shelf_life_days) {
                $warnings[] = "Sisa umur simpan {$left} hari, minimal {$item->min_shelf_life_days} hari.";
            }
        }
        $remaining = $lines->reduce(fn ($c, ReceiptDetail $l) => Qty::add($c, Qty::sub($l->qty_expected_base, $l->qty_received_base)), '0');
        if (Qty::cmp($qtyBase, $remaining) > 0) {
            $warnings[] = 'Qty melebihi sisa ASN: sisa '.Qty::fmt(max(0, (float) $remaining)).', diterima '.Qty::fmt($qtyBase)." {$item->baseUom->code}.";
        }

        // Kapasitas dock: hanya dicek bila LPN baru masuk ke dock ini (menambah isi LPN yang sudah di sini tidak butuh slot)
        $lpnNo = blank($data['lpn_no'] ?? null) ? null : strtoupper(trim($data['lpn_no']));
        $existingLpn = $lpnNo ? Lpn::where('lpn_no', $lpnNo)->first() : null;
        $alreadyHere = $existingLpn && $existingLpn->status === LpnStatus::ACTIVE && $existingLpn->location_id === $location->id;
        if (! $alreadyHere) {
            $dock = $this->capacity->statusFor(collect([$location]))[$location->id];
            if ($dock['full']) {
                $warnings[] = "Dock {$location->code} penuh ({$dock['label']}). Pilih dock lain.";
            }
        }

        if ($warnings) {
            $canConfirm = $user->hasAccess('INB_RECEIVING', 'approve');
            if (empty($data['confirm'])) {
                throw new ConfirmationRequiredException($warnings, $canConfirm);
            }
            if (! $canConfirm) {
                throw new BusinessException('Butuh persetujuan SPV: '.implode(' ', $warnings));
            }
        }

        // ---- LPN
        $lpn = $this->resolveLpnForReceive($data['lpn_no'] ?? null, $header, $location, $user);

        // ---- stok masuk
        $line = $this->pickLine($lines, $lot);
        $condition = ReceivingCondition::from($data['condition'] ?? ReceivingCondition::GOOD->value);

        $this->inventory->changeOnHand(
            new StockAttributes(
                $header->warehouse_id, $location->id, $item->id, $header->owner_id,
                $lot, $exp?->format('Y-m-d'), $lpn->id, $condition->inventoryStatus(),
            ),
            $qtyBase,
            MovementType::RECEIVE,
            new MovementContext($header, $user->id, $data['idempotency_key'] ?? null, 1),
            now(),
        );

        $lpn->update(['status' => LpnStatus::ACTIVE, 'location_id' => $location->id]);

        $line->qty_received_base = Qty::add($line->qty_received_base, $qtyBase);
        if ($condition === ReceivingCondition::DAMAGED) {
            $line->qty_damaged_base = Qty::add($line->qty_damaged_base, $qtyBase);
        }
        $line->save();

        if ($header->status === ReceiptStatus::OPEN) {
            $header->update(['status' => ReceiptStatus::RECEIVING, 'updated_by' => $user->id]);
        }

        return ReceiptReceiving::create([
            'receipt_header_id' => $header->id,
            'receipt_detail_id' => $line->id,
            'item_id' => $item->id,
            'location_id' => $location->id,
            'lpn_id' => $lpn->id,
            'uom_id' => (int) $data['uom_id'],
            'qty' => $data['qty'],
            'conversion_factor' => $factor,
            'qty_base' => $qtyBase,
            'lot_no' => $lot,
            'expiry_date' => $exp,
            'condition' => $condition,
            'source' => $source,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'notes' => $data['notes'] ?? null,
            'received_by' => $user->id,
            'received_at' => now(),
        ]);
    }

    // =================================================================== BATAL & SELESAI

    /** Batalkan satu baris terima (salah scan): stok dibalik lewat ledger. */
    /**
     * Batalkan satu baris terima: stok di dock dikurangi (RECEIVE_CANCEL), qty diterima di detail dikembalikan.
     * Boleh selama barang masih di dock (belum di-putaway):
     * - dokumen RECEIVING  -> tetap RECEIVING
     * - dokumen RECEIVED   -> dibuka ulang ke RECEIVING; selisih lama dihapus (dihitung ulang saat Selesai)
     * Mengembalikan true bila dokumen dibuka ulang.
     */
    public function cancelLine(ReceiptReceiving $line, User $user, ?string $reason): bool
    {
        return DB::transaction(function () use ($line, $user, $reason) {
            $header = ReceiptHeader::whereKey($line->receipt_header_id)->lockForUpdate()->firstOrFail();
            if (! in_array($header->status, [ReceiptStatus::RECEIVING, ReceiptStatus::RECEIVED], true)) {
                throw new BusinessException("Dokumen {$header->receipt_no} berstatus {$header->status->label()}, baris terima tidak bisa dibatalkan.");
            }
            $line = ReceiptReceiving::with(['location.locationClass.locationType', 'lpn'])->whereKey($line->id)->lockForUpdate()->firstOrFail();
            if ($line->isCancelled()) {
                throw new BusinessException('Baris ini sudah dibatalkan.');
            }
            if ($line->isPutaway()) {
                throw new BusinessException("Barang LPN {$line->lpn->lpn_no} sudah disimpan ke rak (putaway), tidak bisa dibatalkan.");
            }
            if ($line->location->locationFunction() !== LocationFunction::RECEIVING) {
                throw new BusinessException("Barang sudah dipindah dari dock ({$line->location->code}), tidak bisa dibatalkan.");
            }
            $detail = ReceiptDetail::whereKey($line->receipt_detail_id)->lockForUpdate()->firstOrFail();
            $lpn = Lpn::whereKey($line->lpn_id)->lockForUpdate()->firstOrFail();

            $this->inventory->changeOnHand(
                new StockAttributes(
                    $header->warehouse_id, $line->location_id, $line->item_id, $header->owner_id,
                    $line->lot_no, $line->expiry_date?->format('Y-m-d'), $line->lpn_id, $line->condition->inventoryStatus(),
                ),
                Qty::sub(0, $line->qty_base),
                MovementType::RECEIVE_CANCEL,
                new MovementContext($header, $user->id, null, 1, 'RECEIVE_CANCEL', $reason),
            );

            $detail->qty_received_base = Qty::sub($detail->qty_received_base, $line->qty_base);
            if ($line->condition === ReceivingCondition::DAMAGED) {
                $detail->qty_damaged_base = Qty::sub($detail->qty_damaged_base, $line->qty_base);
            }
            $detail->save();

            $line->update(['cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            if (! $this->inventory->lpnHasStock($lpn->id)) {
                $lpn->update(['status' => LpnStatus::EMPTY]);
            }

            if ($header->status === ReceiptStatus::RECEIVED) {
                $header->discrepancies()->delete();
                $header->update(['status' => ReceiptStatus::RECEIVING, 'updated_by' => $user->id]);

                return true;
            }

            return false;
        });
    }

    /**
     * Batal banyak baris sekaligus (Intransit In). Tiap baris transaksi sendiri: satu gagal tidak membatalkan yang lain.
     *
     * @param  iterable<ReceiptReceiving>  $lines
     * @return array{done: int, failed: list<string>, reopened: list<string>}
     */
    public function cancelLines(iterable $lines, User $user, string $reason): array
    {
        $result = ['done' => 0, 'failed' => [], 'reopened' => []];
        foreach ($lines as $line) {
            try {
                if ($this->cancelLine($line, $user, $reason)) {
                    $result['reopened'][] = $line->header->receipt_no;
                }
                $result['done']++;
            } catch (BusinessException $e) {
                $result['failed'][] = "{$line->item->sku} ({$line->lpn->lpn_no}): {$e->getMessage()}";
            }
        }
        $result['reopened'] = array_values(array_unique($result['reopened']));

        return $result;
    }

    /** Selesai terima: RECEIVING -> RECEIVED, catat selisih per baris. Mengembalikan jumlah selisih. */
    public function finish(ReceiptHeader $receipt, User $user): int
    {
        return DB::transaction(function () use ($receipt, $user) {
            $header = ReceiptHeader::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if (! $header->status->canTransitionTo(ReceiptStatus::RECEIVED)) {
                throw new BusinessException("Dokumen berstatus {$header->status->label()}, belum/tidak bisa diselesaikan.");
            }

            $count = 0;
            foreach ($header->details()->get() as $d) {
                $diff = Qty::sub($d->qty_received_base, $d->qty_expected_base);
                $cmp = Qty::cmp($diff, 0);
                $rows = [];
                if ($cmp !== 0) {
                    $rows[] = [$cmp < 0 ? DiscrepancyType::SHORT : DiscrepancyType::OVER, $diff];
                }
                if (Qty::cmp($d->qty_damaged_base, 0) > 0) {
                    $rows[] = [DiscrepancyType::DAMAGED, $d->qty_damaged_base];
                }
                foreach ($rows as [$type, $qty]) {
                    $header->discrepancies()->create([
                        'receipt_detail_id' => $d->id,
                        'item_id' => $d->item_id,
                        'type' => $type,
                        'qty_expected_base' => $d->qty_expected_base,
                        'qty_received_base' => $d->qty_received_base,
                        'qty_diff_base' => $qty,
                        'created_by' => $user->id,
                    ]);
                    $count++;
                }
            }

            $header->update(['status' => ReceiptStatus::RECEIVED, 'updated_by' => $user->id]);
            // semua barang sudah di-putaway sebelum Selesai Terima -> langsung CLOSED
            app(PutawayService::class)->closeIfStored($header, $user);

            return $count;
        });
    }

    // =================================================================== RESOLVE (hasil scan)

    /** Lokasi dock hasil scan: harus di gudang dokumen, fungsi RECEIVING, aktif. */
    public function resolveLocation(ReceiptHeader $header, string $code): Location
    {
        $location = Location::with('locationClass.locationType')
            ->where('warehouse_id', $header->warehouse_id)
            ->where('code', strtoupper(trim($code)))
            ->first();
        if (! $location) {
            throw new BusinessException("Lokasi {$code} tidak ditemukan di gudang {$header->warehouse->code}.");
        }
        $this->assertReceivingLocation($location);

        return $location;
    }

    /** Cek LPN hasil scan (belum didaftarkan; pendaftaran terjadi saat Terima). */
    public function inspectLpn(ReceiptHeader $header, string $code): array
    {
        $no = strtoupper(trim($code));
        $lpn = Lpn::with('location')->where('lpn_no', $no)->first();
        if (! $lpn) {
            return ['lpn_no' => $no, 'exists' => false, 'message' => 'LPN baru, otomatis didaftarkan saat terima.'];
        }
        if ($lpn->warehouse_id !== $header->warehouse_id) {
            throw new BusinessException("LPN {$no} terdaftar di gudang lain.");
        }
        if (! $lpn->status->isUsable()) {
            throw new BusinessException("LPN {$no} berstatus {$lpn->status->label()}, tidak bisa dipakai.");
        }

        return [
            'lpn_no' => $lpn->lpn_no, 'exists' => true, 'status' => $lpn->status->label(),
            'active' => $lpn->status === LpnStatus::ACTIVE,
            'location' => $lpn->location?->code,
            'location_id' => $lpn->status === LpnStatus::ACTIVE ? $lpn->location_id : null,
            'message' => $lpn->status === LpnStatus::ACTIVE ? "LPN berisi stok di {$lpn->location?->code}." : 'LPN kosong, siap dipakai.',
        ];
    }

    /** Barcode / SKU hasil scan -> item di dokumen + UOM yang terbaca. */
    public function resolveItem(ReceiptHeader $header, string $code): array
    {
        $code = trim($code);
        $itemIds = $header->details()->pluck('item_id')->unique();
        $uomId = null;

        // 1) barcode kemasan (master_item_uom.barcode) -> UOM kemasan tsb
        $itemUom = ItemUom::whereIn('item_id', $itemIds)->where('barcode', $code)->first();
        if ($itemUom) {
            $item = Item::find($itemUom->item_id);
            $uomId = $itemUom->uom_id;
        } else {
            // 2) barcode item / 3) SKU
            $item = Item::whereIn('id', $itemIds)
                ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', strtoupper($code)))
                ->first();
        }
        if (! $item) {
            $known = Item::where('owner_id', $header->owner_id)
                ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', strtoupper($code)))->exists();
            throw new BusinessException($known
                ? "Item {$code} tidak ada di dokumen {$header->receipt_no}."
                : "Barcode {$code} tidak dikenal.");
        }

        $item->load(['baseUom', 'uoms.uom']);
        $uomId ??= $item->uoms->firstWhere('is_default_inbound', true)?->uom_id ?? $item->base_uom_id;

        return [...$this->itemProgress($header, $item), 'scanned_uom_id' => $uomId];
    }

    // =================================================================== PROGRES

    /** Ringkasan per item: diharapkan / diterima / sisa (base UOM). */
    public function progress(ReceiptHeader $header): array
    {
        $details = $header->details()->with(['item.baseUom', 'item.uoms.uom'])->get();
        $items = $details->groupBy('item_id')->map(fn (Collection $lines) => $this->summarize($lines))->values();

        $expected = $details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_expected_base), '0');
        $received = $details->reduce(fn ($c, $d) => Qty::add($c, $d->qty_received_base), '0');

        return [
            'status' => $header->fresh()->status->value,
            'items' => $items,
            'expected' => (float) $expected,
            'received' => (float) $received,
            'percent' => Qty::cmp($expected, 0) > 0 ? min(100, round($received / $expected * 100)) : 0,
        ];
    }

    private function itemProgress(ReceiptHeader $header, Item $item): array
    {
        $lines = $header->details()->where('item_id', $item->id)->get()->each->setRelation('item', $item);

        return $this->summarize($lines);
    }

    private function summarize(Collection $lines): array
    {
        $item = $lines->first()->item;
        $expected = $lines->reduce(fn ($c, $l) => Qty::add($c, $l->qty_expected_base), '0');
        $received = $lines->reduce(fn ($c, $l) => Qty::add($c, $l->qty_received_base), '0');
        $damaged = $lines->reduce(fn ($c, $l) => Qty::add($c, $l->qty_damaged_base), '0');

        return [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'base_uom' => $item->baseUom->code,
            'uoms' => $item->uomOptions(),
            'lot_required' => $item->is_lot_tracked,
            'expiry_required' => $item->is_expiry_tracked,
            'asn_lots' => $lines->pluck('lot_no')->filter()->unique()->values(),
            'expected' => (float) $expected,
            'received' => (float) $received,
            'damaged' => (float) $damaged,
            'remaining' => max(0, (float) Qty::sub($expected, $received)),
            'percent' => Qty::cmp($expected, 0) > 0 ? min(100, round($received / $expected * 100)) : 0,
        ];
    }

    // =================================================================== helper

    private function assertReceivable(ReceiptHeader $header): void
    {
        if (! in_array($header->status, self::RECEIVABLE, true)) {
            throw new BusinessException("Dokumen {$header->receipt_no} berstatus {$header->status->label()}, tidak bisa menerima barang.");
        }
    }

    private function receivingLocation(ReceiptHeader $header, int $locationId): Location
    {
        $location = Location::with('locationClass.locationType')->lockForUpdate()->find($locationId);
        if (! $location || $location->warehouse_id !== $header->warehouse_id) {
            throw new BusinessException('Lokasi dock tidak valid untuk gudang dokumen ini.');
        }
        $this->assertReceivingLocation($location);

        return $location;
    }

    private function assertReceivingLocation(Location $location): void
    {
        if ($location->locationFunction() !== LocationFunction::RECEIVING) {
            throw new BusinessException("Lokasi {$location->code} bukan lokasi receiving/dock.");
        }
        if ($location->status !== LocationStatus::ACTIVE) {
            throw new BusinessException("Lokasi {$location->code} berstatus {$location->status->label()}.");
        }
    }

    /** LPN untuk menampung barang: nomor kosong = buat baru; nomor belum terdaftar = didaftarkan. */
    private function resolveLpnForReceive(?string $lpnNo, ReceiptHeader $header, Location $location, User $user): Lpn
    {
        $lpn = blank($lpnNo)
            ? $this->lpns->generate($header->warehouse, LpnType::PALLET, null, 1, $user, "Dibuat saat receiving {$header->receipt_no}", Lpn::VIA_RECEIVING)->first()
            : $this->lpns->findOrRegister($lpnNo, $header->warehouse, $user);

        $lpn = Lpn::with('location')->whereKey($lpn->id)->lockForUpdate()->firstOrFail();

        if ($lpn->status === LpnStatus::ACTIVE && $lpn->location_id !== $location->id) {
            throw new BusinessException("LPN {$lpn->lpn_no} sedang berisi stok di lokasi {$lpn->location?->code}. Gunakan LPN lain.");
        }
        $otherOwner = Inventory::where('lpn_id', $lpn->id)->where('qty_on_hand', '>', 0)
            ->where('owner_id', '!=', $header->owner_id)->exists();
        if ($otherOwner) {
            throw new BusinessException("LPN {$lpn->lpn_no} berisi barang milik owner lain.");
        }

        return $lpn;
    }

    /**
     * Pilih baris detail ASN untuk dibebani: lot sama & masih ada sisa -> baris tanpa lot & ada sisa
     * -> baris mana pun yang ada sisa -> baris terakhir (lebih terima).
     */
    private function pickLine(Collection $lines, ?string $lot): ReceiptDetail
    {
        $hasRemaining = fn (ReceiptDetail $l) => Qty::cmp($l->qty_received_base, $l->qty_expected_base) < 0;

        return $lines->first(fn ($l) => $lot && strtoupper((string) $l->lot_no) === $lot && $hasRemaining($l))
            ?? $lines->first(fn ($l) => ! $l->lot_no && $hasRemaining($l))
            ?? $lines->first($hasRemaining)
            ?? $lines->last();
    }
}
