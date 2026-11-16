<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * php artisan portal:install
 * Menyiapkan database PUSAT (wms_central): buat database bila belum ada, migrasi tabel tenant & tenant_login,
 * dan mendaftarkan database .env (DB_DATABASE) sebagai site "demo" supaya user contoh bisa login (superadmin@demo).
 * Aman dijalankan berulang (dipanggil start-wms.bat).
 */
class PortalInstall extends Command
{
    protected $signature = 'portal:install {--demo-code=demo : kode site untuk database .env}';

    protected $description = 'Siapkan database pusat portal (tenant) dan daftarkan database .env sebagai site demo';

    public function handle(): int
    {
        $central = config('database.connections.central.database');
        DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$central}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::purge('central');
        $this->info("Database pusat: {$central}");

        Artisan::call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true], $this->output);

        $code = strtolower($this->option('demo-code'));
        $db = config('database.connections.mysql.database');
        $tenant = Tenant::firstOrCreate(['code' => $code], [
            'name' => 'Site Demo', 'db_name' => $db, 'warehouse_type' => 'FMCG', 'company' => 'PT Sumber Pangan Sejahtera',
            'admin_username' => 'superadmin', 'status' => Tenant::STATUS_ACTIVE, 'provisioned_at' => now(),
        ]);
        $this->info("Site {$tenant->code} -> database {$tenant->db_name}. Login: superadmin@{$tenant->code} / password");

        return self::SUCCESS;
    }
}
