<?php

namespace App\Models;

use App\Enums\RotationRule;
use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Header shipment (pesanan keluar). Ubah status hanya lewat service outbound. */
class ShipmentHeader extends Model
{
    protected $table = 'o_shipment_header';

    protected $fillable = [
        'shipment_no', 'warehouse_id', 'owner_id', 'customer_id', 'customer_address_id', 'ship_to_name', 'ship_to_address',
        'reference_no', 'order_date', 'expected_ship_date', 'allocation_rule', 'priority', 'status', 'wave_id', 'delivery_order_id',
        'notes', 'created_by', 'updated_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'allocation_rule' => RotationRule::class,
            'status' => ShipmentStatus::class,
            'priority' => 'integer',
            'order_date' => 'date',
            'expected_ship_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShipmentDetail::class, 'shipment_header_id')->orderBy('line_no');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'shipment_header_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(WaveHeader::class, 'wave_id');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderHeader::class, 'delivery_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Aturan alokasi efektif untuk satu item: wave > shipment > item. Butuh relasi wave bila ada. */
    public function ruleFor(Item $item, ?WaveHeader $wave = null): RotationRule
    {
        return $wave?->allocation_rule ?? $this->allocation_rule ?? $item->rotation_rule ?? RotationRule::FIFO;
    }
}
