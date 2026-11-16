<?php

namespace App\Support;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Pindah database aktif ke database tenant. Koneksi 'mysql' dipakai semua model WMS; di sini nama
 * database-nya diganti lalu koneksinya dibuka ulang. Model pusat (App\Models\Central\*) memakai
 * koneksi 'central' secara eksplisit sehingga tidak terpengaruh.
 */
class Tenancy
{
    private static ?Tenant $current = null;

    public static function use(Tenant $tenant): void
    {
        if (self::$current?->id === $tenant->id && Config::get('database.connections.mysql.database') === $tenant->db_name) {
            return;
        }
        self::$current = $tenant;
        Config::set('database.connections.mysql.database', $tenant->db_name);
        DB::purge('mysql');                 // tutup koneksi lama -> query berikutnya membuka database tenant
        DB::setDefaultConnection('mysql');
        // cache (rate limit login, dedupe notifikasi, dll.) dipisah per tenant
        try {
            Cache::store()->setPrefix(rtrim(Config::get('cache.prefix', 'wms'), '_:').':t'.$tenant->id.':');
        } catch (\Throwable) {
            // store tanpa setPrefix (mis. array) -> abaikan
        }
    }

    /** Pakai database bernama $db tanpa model Tenant (dipakai saat provisioning). */
    public static function useDatabase(string $db): void
    {
        self::$current = null;
        Config::set('database.connections.mysql.database', $db);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
    }

    public static function current(): ?Tenant
    {
        return self::$current;
    }

    /** Cari tenant aktif dari kode (cache 60 detik di memori proses / cache store pusat). */
    public static function find(string $code): ?Tenant
    {
        $code = strtolower(trim($code));
        if ($code === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]{1,19}$/', $code)) {
            return null;
        }

        return Tenant::where('code', $code)->first();
    }

    /** Pisahkan "username@site" -> [username, site]. Tanpa @ -> site null. */
    public static function splitLogin(string $login): array
    {
        $login = trim($login);
        $pos = strrpos($login, '@');
        if ($pos === false) {
            return [$login, null];
        }

        return [substr($login, 0, $pos), strtolower(substr($login, $pos + 1))];
    }
}
