<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\ModuleDetail;
use App\Support\Tenancy;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleAccessSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * php artisan portal:upgrade
 * Menyamakan SEMUA database site dengan kode terbaru: migrate skema, daftar menu/modul (ModuleSeeder), dan
 * hak akses default hanya untuk modul yang BARU muncul (pengaturan hak akses yang sudah diubah user tidak ditimpa).
 * Aman dijalankan berulang (dipanggil start-wms.bat).
 */
class PortalUpgrade extends Command
{
    protected $signature = 'portal:upgrade {--site= : hanya satu kode site}';

    protected $description = 'Migrate + daftar modul baru untuk semua database site';

    public function handle(): int
    {
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->when($this->option('site'), fn ($q, $c) => $q->where('code', $c))->orderBy('code')->get();
        foreach ($tenants as $t) {
            try {
                Tenancy::use($t);
                Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
                $before = ModuleDetail::pluck('code')->all();
                (new ModuleSeeder)->run();
                $new = array_values(array_diff(ModuleDetail::pluck('code')->all(), $before));
                if ($new) {
                    $seeder = new RoleAccessSeeder;
                    $seeder->only = $new;
                    $seeder->run();
                }
                $this->info("{$t->code}: OK".($new ? ' (modul baru: '.implode(', ', $new).')' : ''));
            } catch (\Throwable $e) {
                $this->error("{$t->code}: GAGAL - ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
