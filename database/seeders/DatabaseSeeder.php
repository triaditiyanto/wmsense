<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate:fresh --seed   -> reset database + isi data awal
     * php artisan db:seed --class=ModuleSeeder   -> jalankan satu seeder saja
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            RoleAccessSeeder::class,
            UserSeeder::class,
            DemoMasterSeeder::class,
            DemoReceiptSeeder::class,
            DemoStockSeeder::class,
            DemoLocatingSeeder::class,
            DemoShipmentSeeder::class,
        ]);
    }
}
