<?php

namespace App\Models;

use App\Enums\ReceivingCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kali "Terima" (scan). Dibuat & dibatalkan hanya lewat ReceivingService. */
class ReceiptReceiving extends Model
{
    public const SOURCE_DESKTOP = 'DESKTOP';
    public const SOURCE_MOBILE = 'MOBILE';

    protected $table = 'o_receipt_receiving';

    protected $fillable = [
        'receipt_header_id', 'receipt_detail_id', 'item_id', 'location_id', 'lpn_id',
        'uom_id', 'qty', 'conversion_factor', 'qty_base', 'lot_no', 'expiry_date', 'condition',
        'source', 'idempotency_key', 'notes', 'received_by', 'received_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason', 'putaway_task_id', 'putaway_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'conversion_factor' => 'decimal:6',
            'qty_base' => 'decimal:4',
            'expiry_date' => 'date',
            'condition' => ReceivingCondition::class,
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'putaway_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Sudah disimpan ke rak (putaway selesai). */
    public function isPutaway(): bool
    {
        return $this->putaway_at !== null;
    }

    public function putawayTask(): BelongsTo
    {
        return $this->belongsTo(TaskHeader::class, 'putaway_task_id');
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(ReceiptHeader::class, 'receipt_header_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ReceiptDetail::class, 'receipt_detail_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
