<?php

namespace App\Models\Central;

use App\Support\WarehouseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Site / klien SaaS (database pusat). Satu tenant = satu database WMS sendiri. */
class Tenant extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_PROVISIONING = 'PROVISIONING';
    public const STATUS_FAILED = 'FAILED';

    protected $connection = 'central';

    protected $table = 'tenant';

    protected $fillable = ['code', 'name', 'db_name', 'warehouse_type', 'company', 'email', 'phone', 'admin_username', 'status', 'provision_error', 'provisioned_at'];

    protected function casts(): array
    {
        return ['provisioned_at' => 'datetime'];
    }

    public function logins(): HasMany
    {
        return $this->hasMany(TenantLogin::class, 'tenant_id');
    }

    public function warehouseType(): ?WarehouseType
    {
        return WarehouseType::tryFrom($this->warehouse_type);
    }
}
