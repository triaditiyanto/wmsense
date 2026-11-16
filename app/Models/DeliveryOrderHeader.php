<?php

namespace App\Models;

use App\Enums\DeliveryOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DO / Surat Jalan: kumpulan shipment yang berangkat bersama. Detailnya = shipment (o_shipment_header.delivery_order_id). */
class DeliveryOrderHeader extends Model
{
    protected $table = 'o_delivery_order_header';

    protected $fillable = [
        'do_no', 'warehouse_id', 'status', 'ship_date', 'vehicle_no', 'driver_name', 'driver_phone', 'carrier', 'notes',
        'print_count', 'last_printed_at', 'created_by', 'dispatched_by', 'dispatched_at', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryOrderStatus::class,
            'ship_date' => 'date',
            'print_count' => 'integer',
            'last_printed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(ShipmentHeader::class, 'delivery_order_id')->orderBy('id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
