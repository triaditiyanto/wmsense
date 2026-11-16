<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database PUSAT (koneksi 'central', bukan database tenant). Dijalankan oleh `php artisan portal:install`.
 *   tenant       : site / klien SaaS -> nama database sendiri (wms_<code>)
 *   tenant_login : catatan tiap sesi login user tenant (siapa, kapan, terakhir aktif, logout) untuk monitoring portal
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('tenant', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();         // dipakai saat login: username@code
            $table->string('name', 100);
            $table->string('db_name', 64)->unique();
            $table->string('warehouse_type', 20);         // App\Support\WarehouseType
            $table->string('company', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('admin_username', 50);
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE | SUSPENDED | PROVISIONING | FAILED
            $table->text('provision_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('tenant_login', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('username', 50);
            $table->string('user_name', 100);
            $table->string('role', 50)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('device', 20)->nullable();      // DESKTOP | MOBILE
            $table->timestamp('login_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('logout_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'login_at']);
            $table->index(['last_seen_at', 'logout_at']);
            $table->index(['tenant_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_login');
        Schema::connection('central')->dropIfExists('tenant');
    }
};
