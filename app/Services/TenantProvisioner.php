<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Central\Tenant;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use App\Support\WarehouseType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membuat site baru dari portal registrasi:
 *   1. catat tenant di database pusat (status PROVISIONING)
 *   2. CREATE DATABASE wms_<code>
 *   3. jalankan migration WMS di database itu
 *   4. seed data sistem (role, modul, hak akses), buat user admin site (role Superadmin), seed master dummy sesuai tipe gudang
 *   5. tenant ACTIVE. Gagal di tengah -> status FAILED + pesan error (database dibiarkan untuk diperiksa).
 * Dipanggil sinkron dari request registrasi (beberapa detik). Setelah selesai koneksi dikembalikan ke database semula.
 */
class TenantProvisioner
{
    public function provision(array $data): Tenant
    {
        $code = strtolower(trim($data['code']));
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{2,19}$/', $code)) {
            throw new BusinessException('Kode site: 3-20 huruf kecil/angka/-/_ dan diawali huruf/angka.');
        }
        if (Tenant::where('code', $code)->exists()) {
            throw new BusinessException("Kode site \"{$code}\" sudah dipakai. Pilih kode lain.");
        }
        $type = WarehouseType::from($data['warehouse_type']);
        $db = 'wms_'.str_replace('-', '_', $code);

        $tenant = Tenant::create([
            'code' => $code, 'name' => $data['name'], 'db_name' => $db, 'warehouse_type' => $type->value,
            'company' => $data['company'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'admin_username' => $data['admin_username'], 'status' => Tenant::STATUS_PROVISIONING,
        ]);

        $originalDb = Config::get('database.connections.mysql.database');
        try {
            DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            Tenancy::useDatabase($db);

            Artisan::call('migrate', ['--database' => 'mysql', '--path' => 'database/migrations', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\TenantSeeder', '--database' => 'mysql', '--force' => true]);

            $admin = User::create([
                'username' => $data['admin_username'], 'name' => $data['admin_name'], 'email' => $data['email'] ?? null,
                'password' => $data['admin_password'], 'role_id' => Role::where('code', 'SUPERADMIN')->value('id'), 'is_active' => true,
            ]);
            Config::set('tenant.provision', ['type' => $type, 'name' => $data['name'], 'company' => $data['company'] ?? $data['name'], 'admin' => $admin]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\TenantMasterSeeder', '--database' => 'mysql', '--force' => true]);

            $tenant->update(['status' => Tenant::STATUS_ACTIVE, 'provisioned_at' => now(), 'provision_error' => null]);
        } catch (Throwable $e) {
            $tenant->update(['status' => Tenant::STATUS_FAILED, 'provision_error' => mb_substr($e->getMessage(), 0, 2000)]);
            report($e);
            throw new BusinessException('Gagal menyiapkan database site: '.$e->getMessage());
        } finally {
            Tenancy::useDatabase($originalDb);
        }

        return $tenant;
    }
}
