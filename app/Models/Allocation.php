<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Enums\RotationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris alokasi = satu baris pick list (stok sumber tertentu untuk satu baris shipment). */
class Allocation extends Model
{
    protected $table = 'o_allocation';

    protected $fillable = [
        'wave_id', 'shipment_header_id', 'shipment_detail_id', 'inventory_id', 'location_id', 'lpn_id', 'item_id', 'owner_id',
        'lot_no', 'expiry_date', 'qty_allocated_base', 'qty_picked_base', 'status', 'rule', 'staging_location_id',
        'picked_by', 'picked_at', 'pick_source', 'short_reason',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'qty_allocated_base' => 'decimal:4',
            'qty_picked_base' => 'decimal:4',
            'status' => AllocationStatus::class,
            'rule' => RotationRule::class,
            'picked_at' => 'datetime',
        ];
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(WaveHeader::class, 'wave_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class, 'shipment_header_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ShipmentDetail::class, 'shipment_detail_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stagingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'staging_location_id');
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }
}
