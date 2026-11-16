<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Detail dokumen penerimaan (item yang diharapkan datang). */
class ReceiptDetail extends Model
{
    protected $table = 'o_receipt_detail';

    protected $fillable = [
        'receipt_header_id', 'line_no', 'item_id', 'uom_id',
        'qty_expected', 'conversion_factor', 'qty_expected_base', 'qty_received_base', 'qty_damaged_base',
        'lot_no', 'expiry_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'qty_expected' => 'decimal:4',
            'conversion_factor' => 'decimal:6',
            'qty_expected_base' => 'decimal:4',
            'qty_received_base' => 'decimal:4',
            'qty_damaged_base' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(ReceiptHeader::class, 'receipt_header_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
