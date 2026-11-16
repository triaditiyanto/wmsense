<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Header dokumen penerimaan. Ubah status hanya lewat ReceiptService. */
class ReceiptHeader extends Model
{
    protected $table = 'o_receipt_header';

    protected $fillable = [
        'receipt_no', 'warehouse_id', 'owner_id', 'receipt_type', 'status',
        'reference_no', 'supplier_id', 'customer_id', 'expected_date', 'notes',
        'created_by', 'updated_by', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'receipt_type' => ReceiptType::class,
            'status' => ReceiptStatus::class,
            'expected_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ReceiptDetail::class)->orderBy('line_no');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(ReceiptReceiving::class)->latest('id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReceiptDiscrepancy::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Nama pengirim untuk tampilan (supplier atau customer, tergantung tipe). Butuh eager load. */
    public function senderName(): ?string
    {
        return $this->receipt_type->usesCustomer() ? $this->customer?->name : $this->supplier?->name;
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
}
