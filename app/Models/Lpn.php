<?php

namespace App\Models;

use App\Enums\LpnStatus;
use App\Enums\LpnType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LPN = lokasi bergerak. Ubah status/lokasi hanya lewat LpnService
 * (nanti juga lewat InventoryService saat stok masuk/keluar LPN).
 */
class Lpn extends Model
{
    public const VIA_MASTER = 'MASTER';
    public const VIA_RECEIVING = 'RECEIVING';

    protected $table = 'master_lpn';

    protected $fillable = [
        'lpn_no', 'warehouse_id', 'lpn_type', 'pallet_id', 'status', 'location_id',
        'registered_via', 'registered_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'lpn_type' => LpnType::class,
            'status' => LpnStatus::class,
        ];
    }

    /** Nomor LPN selalu disimpan huruf besar tanpa spasi (sesuai hasil scan). */
    protected function setLpnNoAttribute(?string $value): void
    {
        $this->attributes['lpn_no'] = $value === null ? null : strtoupper(trim($value));
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
