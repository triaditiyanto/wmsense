<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Isi awal database site BARU (dipanggil TenantProvisioner): data sistem (role, modul, hak akses)
 * + master data dummy sesuai tipe gudang (TenantMasterSeeder). TANPA user contoh, TANPA transaksi/stok.
 * Tipe gudang & info site dibaca dari config('tenant.provision') yang diisi provisioner.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            RoleAccessSeeder::class,
            TenantMasterSeeder::class,
        ]);
    }
}
