<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alamat kirim customer (detail). Dipakai outbound order sebagai tujuan pengiriman. */
class CustomerAddress extends Model
{
    protected $table = 'master_customer_address';

    protected $fillable = ['customer_id', 'label', 'address', 'city', 'postal_code', 'pic', 'phone', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Satu baris ringkas: "Jl. ... , Bekasi 17111" */
    public function fullAddress(): string
    {
        return trim($this->address.($this->city ? ', '.$this->city : '').($this->postal_code ? ' '.$this->postal_code : ''));
    }
}
