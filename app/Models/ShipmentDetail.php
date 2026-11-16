<?php

namespace App\Models;

use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Baris shipment: item & qty pesanan + progres alokasi / pick / kirim (semua dalam base UOM). */
class ShipmentDetail extends Model
{
    protected $table = 'o_shipment_detail';

    protected $fillable = [
        'shipment_header_id', 'line_no', 'item_id', 'uom_id', 'qty_ordered', 'conversion_factor', 'qty_ordered_base',
        'qty_allocated_base', 'qty_picked_base', 'qty_shipped_base', 'lot_no', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'qty_ordered' => 'decimal:4',
            'conversion_factor' => 'decimal:6',
            'qty_ordered_base' => 'decimal:4',
            'qty_allocated_base' => 'decimal:4',
            'qty_picked_base' => 'decimal:4',
            'qty_shipped_base' => 'decimal:4',
        ];
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class, 'shipment_header_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'shipment_detail_id');
    }

    /**
     * Sisa yang belum teralokasi (base). qty_allocated_base = total alokasi yang masih berlaku
     * (termasuk yang sudah dipick; short pick mengurangi angka ini), jadi selalu >= qty_picked_base.
     */
    public function qtyOpenBase(): string
    {
        return Qty::sub($this->qty_ordered_base, $this->qty_allocated_base);
    }

    /** Konversi base -> UOM pesanan untuk tampilan. */
    public function toOrderUom(string|int|float $base): string
    {
        return bcdiv((string) $base, $this->conversion_factor, 4);
    }
}
