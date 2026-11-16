<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Isi yang dipindah oleh satu task (dicatat saat task selesai). */
class TaskDetail extends Model
{
    protected $table = 'o_task_detail';

    protected $fillable = ['task_header_id', 'item_id', 'owner_id', 'lot_no', 'expiry_date', 'inventory_status', 'qty_base', 'received_at'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'inventory_status' => InventoryStatus::class,
            'qty_base' => 'decimal:4',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TaskHeader::class, 'task_header_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
