<?php

namespace App\Models;

use App\Enums\DiscrepancyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptDiscrepancy extends Model
{
    protected $table = 'o_receipt_discrepancy';

    protected $fillable = [
        'receipt_header_id', 'receipt_detail_id', 'item_id', 'type',
        'qty_expected_base', 'qty_received_base', 'qty_diff_base', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscrepancyType::class,
            'qty_expected_base' => 'decimal:4',
            'qty_received_base' => 'decimal:4',
            'qty_diff_base' => 'decimal:4',
        ];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ReceiptDetail::class, 'receipt_detail_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
