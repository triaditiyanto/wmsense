<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module detail = menu/fitur di dalam module. Hak akses diberikan per baris ini.
     * code dipakai di kode program (middleware access:INB_RECEIPT,create), jadi jangan diubah.
     */
    public function up(): void
    {
        Schema::create('master_module_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('master_module')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('route_name', 100)->nullable(); // null = belum ada halamannya
            $table->string('menu_group', 50)->nullable();   // pengelompokan dropdown di navbar
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_module_detail');
    }
};
