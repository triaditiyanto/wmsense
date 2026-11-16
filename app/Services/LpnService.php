<?php

namespace App\Services;

use App\Enums\LpnStatus;
use App\Enums\LpnType;
use App\Exceptions\BusinessException;
use App\Models\Lpn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registrasi & siklus hidup LPN (lokasi bergerak).
 * Dua jalur registrasi:
 *   1. Master  : register() nomor manual / generate() nomor otomatis (label dicetak duluan).
 *   2. Receiving: findOrRegister() saat operator scan LPN yang belum terdaftar.
 */
class LpnService
{
    public const MAX_GENERATE = 500;

    public function __construct(private DocumentNumberService $numbers)
    {
    }

    /** Generate $count LPN baru dengan nomor otomatis LPN-WH1-26-000001. */
    public function generate(Warehouse $warehouse, LpnType $type, ?int $palletId, int $count, User $by, ?string $notes = null, string $via = Lpn::VIA_MASTER): Collection
    {
        if ($count < 1 || $count > self::MAX_GENERATE) {
            throw new BusinessException('Jumlah LPN harus antara 1 dan '.self::MAX_GENERATE.'.');
        }

        return DB::transaction(function () use ($warehouse, $type, $palletId, $count, $by, $notes, $via) {
            return collect($this->numbers->nextBatch('LPN', $warehouse, $count))
                ->map(fn (string $no) => Lpn::create([
                    'lpn_no' => $no,
                    'warehouse_id' => $warehouse->id,
                    'lpn_type' => $type,
                    'pallet_id' => $type === LpnType::PALLET ? $palletId : null,
                    'status' => LpnStatus::EMPTY,
                    'registered_via' => $via,
                    'registered_by' => $by->id,
                    'notes' => $notes,
                ]));
        });
    }

    /** Registrasi satu LPN dengan nomor yang sudah ada (label pre-printed / SSCC supplier). */
    public function register(array $data, User $by): Lpn
    {
        $type = LpnType::from($data['lpn_type']);

        return Lpn::create([
            'lpn_no' => $data['lpn_no'],
            'warehouse_id' => $data['warehouse_id'],
            'lpn_type' => $type,
            'pallet_id' => $type === LpnType::PALLET ? ($data['pallet_id'] ?? null) : null,
            'status' => LpnStatus::EMPTY,
            'registered_via' => Lpn::VIA_MASTER,
            'registered_by' => $by->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /** Ubah atribut (bukan status/lokasi). */
    public function update(Lpn $lpn, array $data): Lpn
    {
        if (! $lpn->status->isUsable()) {
            throw new BusinessException("LPN {$lpn->lpn_no} berstatus {$lpn->status->label()} dan tidak bisa diubah.");
        }
        $type = LpnType::from($data['lpn_type']);
        $lpn->update([
            'lpn_type' => $type,
            'pallet_id' => $type === LpnType::PALLET ? ($data['pallet_id'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $lpn;
    }

    public function void(Lpn $lpn): Lpn
    {
        return DB::transaction(function () use ($lpn) {
            $lpn = Lpn::whereKey($lpn->id)->lockForUpdate()->firstOrFail();

            if (! $lpn->status->canTransitionTo(LpnStatus::VOID)) {
                throw new BusinessException("LPN {$lpn->lpn_no} berstatus {$lpn->status->label()}, hanya LPN kosong yang bisa di-void.");
            }
            if ($lpn->inventory()->where('qty_on_hand', '>', 0)->exists()) {
                throw new BusinessException("LPN {$lpn->lpn_no} masih berisi stok.");
            }
            $lpn->update(['status' => LpnStatus::VOID, 'location_id' => null]);

            return $lpn;
        });
    }

    /**
     * Dipakai saat RECEIVING: cari LPN hasil scan, daftarkan otomatis bila belum ada.
     * Aman dari scan bersamaan (createOrFirst: INSERT dulu, bila bentrok unique -> ambil yang ada).
     */
    public function findOrRegister(string $lpnNo, Warehouse $warehouse, User $by, LpnType $type = LpnType::PALLET): Lpn
    {
        $lpnNo = strtoupper(trim($lpnNo));
        if ($lpnNo === '') {
            throw new BusinessException('Nomor LPN kosong.');
        }

        $lpn = Lpn::createOrFirst(['lpn_no' => $lpnNo], [
            'warehouse_id' => $warehouse->id,
            'lpn_type' => $type,
            'status' => LpnStatus::EMPTY,
            'registered_via' => Lpn::VIA_RECEIVING,
            'registered_by' => $by->id,
        ]);

        if ($lpn->warehouse_id !== $warehouse->id) {
            throw new BusinessException("LPN {$lpnNo} terdaftar di gudang lain.");
        }
        if (! $lpn->status->isUsable()) {
            throw new BusinessException("LPN {$lpnNo} berstatus {$lpn->status->label()} dan tidak bisa dipakai.");
        }

        return $lpn;
    }
}
