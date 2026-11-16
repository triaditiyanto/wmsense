<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matriks hak akses: role x module detail x aksi.
     * Tidak ada baris = tidak punya akses sama sekali.
     */
    public function up(): void
    {
        Schema::create('master_role_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('master_role')->cascadeOnDelete();
            $table->foreignId('module_detail_id')->constrained('master_module_detail')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'module_detail_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_role_access');
    }
};
