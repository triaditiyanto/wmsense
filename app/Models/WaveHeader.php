<?php

namespace App\Models;

use App\Enums\RotationRule;
use App\Enums\WaveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Wave outbound (o_wave_header). Ubah status hanya lewat WaveService / PickingService. */
class WaveHeader extends Model
{
    public const TYPE_GO = 'GO';     // satu shipment, dibuat dari tombol Go Wave di shipment
    public const TYPE_PLAN = 'PLAN'; // wave planning: banyak shipment

    protected $table = 'o_wave_header';

    protected $fillable = [
        'wave_no', 'warehouse_id', 'wave_type', 'allocation_rule', 'status', 'staging_location_id', 'notes',
        'created_by', 'allocated_by', 'allocated_at', 'picking_started_at', 'picked_at', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'allocation_rule' => RotationRule::class,
            'status' => WaveStatus::class,
            'allocated_at' => 'datetime',
            'picking_started_at' => 'datetime',
            'picked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(ShipmentHeader::class, 'wave_id')->orderBy('priority')->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'wave_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stagingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'staging_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function typeLabel(): string
    {
        return $this->wave_type === self::TYPE_GO ? 'Go Wave' : 'Wave Planning';
    }
}
