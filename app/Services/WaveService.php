<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\ShipmentStatus;
use App\Enums\WaveStatus;
use App\Exceptions\BusinessException;
use App\Models\ShipmentHeader;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WaveHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wave = kumpulan shipment yang dialokasikan & dipick bersama.
 *   Wave Planning (PLAN): pilih banyak shipment OPEN -> wave PLANNED -> Run Wave (alokasi) -> ALLOCATED.
 *   Go Wave (GO)        : satu shipment dari halaman shipment, langsung dialokasikan.
 * Shipment hanya boleh berada di satu wave aktif. Alokasi ulang & batal hanya selama belum ada yang dipick.
 */
class WaveService
{
    public function __construct(private DocumentNumberService $numbers, private AllocationService $allocation) {}

    /** Buat wave dari shipment OPEN (semua harus satu gudang). */
    public function create(array $data, array $shipmentIds, User $user, string $type = WaveHeader::TYPE_PLAN): WaveHeader
    {
        return DB::transaction(function () use ($data, $shipmentIds, $user, $type) {
            $shipments = $this->lockOpenShipments($shipmentIds);
            $warehouseId = (int) ($data['warehouse_id'] ?? $shipments->first()->warehouse_id);
            $wave = WaveHeader::create([
                'wave_no' => $this->numbers->next('WAV', Warehouse::findOrFail($warehouseId)),
                'warehouse_id' => $warehouseId,
                'wave_type' => $type,
                'allocation_rule' => ($data['allocation_rule'] ?? null) ?: null,
                'status' => WaveStatus::PLANNED,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);
            $this->attach($wave, $shipments, $user);

            return $wave;
        });
    }

    /** Go Wave: wave berisi satu shipment, langsung dialokasikan. @return array{wave: WaveHeader, summary: array} */
    public function goWave(ShipmentHeader $shipment, ?string $rule, User $user): array
    {
        return DB::transaction(function () use ($shipment, $rule, $user) {
            $wave = $this->create(['warehouse_id' => $shipment->warehouse_id, 'allocation_rule' => $rule], [$shipment->id], $user, WaveHeader::TYPE_GO);

            return ['wave' => $wave, 'summary' => $this->allocate($wave, $user)];
        });
    }

    public function addShipments(WaveHeader $wave, array $shipmentIds, User $user): WaveHeader
    {
        return DB::transaction(function () use ($wave, $shipmentIds, $user) {
            $wave = $this->lock($wave);
            if (! in_array($wave->status, [WaveStatus::PLANNED, WaveStatus::ALLOCATED], true)) {
                throw new BusinessException("Wave {$wave->wave_no} sudah {$wave->status->label()}, tidak bisa ditambah shipment.");
            }
            if ($wave->wave_type === WaveHeader::TYPE_GO) {
                throw new BusinessException('Go Wave hanya berisi satu shipment. Buat wave lewat Wave Planning.');
            }
            $shipments = $this->lockOpenShipments($shipmentIds);
            if ($shipments->contains(fn ($s) => $s->warehouse_id !== $wave->warehouse_id)) {
                throw new BusinessException('Shipment harus dari gudang yang sama dengan wave.');
            }
            $this->attach($wave, $shipments, $user);

            return $wave;
        });
    }

    /** Lepas shipment dari wave (alokasi yang belum dipick dilepas). Shipment kembali OPEN. */
    public function removeShipment(WaveHeader $wave, ShipmentHeader $shipment, User $user): void
    {
        DB::transaction(function () use ($wave, $shipment, $user) {
            $wave = $this->lock($wave);
            $shipment = ShipmentHeader::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($shipment->wave_id !== $wave->id) {
                throw new BusinessException("Shipment {$shipment->shipment_no} tidak ada di wave ini.");
            }
            if (! in_array($shipment->status, [ShipmentStatus::WAVED, ShipmentStatus::ALLOCATED], true)) {
                throw new BusinessException("Shipment {$shipment->shipment_no} sudah {$shipment->status->label()}, tidak bisa dilepas.");
            }
            $this->allocation->release($shipment->allocations()->where('status', AllocationStatus::OPEN)->lockForUpdate()->get(), $user, 'UNWAVE');
            $shipment->update(['status' => ShipmentStatus::OPEN, 'wave_id' => null, 'updated_by' => $user->id]);
        });
    }

    /** Run Wave: alokasikan sisa semua baris. Boleh diulang (hanya mengisi yang belum teralokasi). */
    public function allocate(WaveHeader $wave, User $user): array
    {
        return DB::transaction(function () use ($wave, $user) {
            $wave = $this->lock($wave);
            if (! $wave->status->canAllocate()) {
                throw new BusinessException("Wave {$wave->wave_no} berstatus {$wave->status->label()}, tidak bisa dialokasikan.");
            }
            if (! $wave->shipments()->exists()) {
                throw new BusinessException('Wave belum berisi shipment.');
            }
            $summary = $this->allocation->allocateWave($wave, $user);
            $wave->update([
                'status' => $wave->status === WaveStatus::PLANNED ? WaveStatus::ALLOCATED : $wave->status,
                'allocated_by' => $user->id, 'allocated_at' => now(),
            ]);

            return $summary;
        });
    }

    /** Alokasi ulang: lepas semua alokasi yang belum dipick, lalu alokasikan lagi (mis. aturan wave diganti). */
    public function reallocate(WaveHeader $wave, User $user, ?string $rule = null): array
    {
        return DB::transaction(function () use ($wave, $user, $rule) {
            $wave = $this->lock($wave);
            if (! in_array($wave->status, [WaveStatus::ALLOCATED, WaveStatus::PICKING], true)) {
                throw new BusinessException("Wave {$wave->wave_no} berstatus {$wave->status->label()}, tidak bisa dialokasi ulang.");
            }
            if ($rule !== null) {
                $wave->update(['allocation_rule' => $rule ?: null]);
            }
            $this->allocation->release($wave->allocations()->where('status', AllocationStatus::OPEN)->lockForUpdate()->get(), $user, 'REALLOCATE');

            return $this->allocate($wave, $user);
        });
    }

    public function cancel(WaveHeader $wave, User $user): void
    {
        DB::transaction(function () use ($wave, $user) {
            $wave = $this->lock($wave);
            if (! $wave->status->canCancel()) {
                throw new BusinessException("Wave {$wave->wave_no} berstatus {$wave->status->label()}, tidak bisa dibatalkan.");
            }
            $this->allocation->release($wave->allocations()->where('status', AllocationStatus::OPEN)->lockForUpdate()->get(), $user, 'WAVE_CANCEL');
            $wave->shipments()->lockForUpdate()->get()->each(fn ($s) => $s->update(['status' => ShipmentStatus::OPEN, 'wave_id' => null, 'updated_by' => $user->id]));
            $wave->update(['status' => WaveStatus::CANCELLED, 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
        });
    }

    // ---------------------------------------------------------------- helper

    private function lock(WaveHeader $wave): WaveHeader
    {
        return WaveHeader::whereKey($wave->id)->lockForUpdate()->firstOrFail();
    }

    /** @return Collection<int, ShipmentHeader> */
    private function lockOpenShipments(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            throw new BusinessException('Pilih minimal satu shipment.');
        }
        $shipments = ShipmentHeader::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($shipments->count() !== count($ids)) {
            throw new BusinessException('Ada shipment yang tidak ditemukan.');
        }
        foreach ($shipments as $s) {
            if ($s->status !== ShipmentStatus::OPEN) {
                throw new BusinessException("Shipment {$s->shipment_no} berstatus {$s->status->label()} (harus Open).");
            }
        }
        if ($shipments->pluck('warehouse_id')->unique()->count() > 1) {
            throw new BusinessException('Semua shipment dalam satu wave harus dari gudang yang sama.');
        }

        return $shipments;
    }

    private function attach(WaveHeader $wave, Collection $shipments, User $user): void
    {
        foreach ($shipments as $s) {
            $s->update(['status' => ShipmentStatus::WAVED, 'wave_id' => $wave->id, 'updated_by' => $user->id]);
        }
    }
}
