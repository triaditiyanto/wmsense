<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Role user berjenjang. level kecil = jabatan lebih tinggi
     * (0 Superadmin, 1 Manager, 2 SPV, 3 Admin, 4 Operator).
     * User hanya boleh mengelola user/role dengan level LEBIH BESAR dari dirinya.
     */
    public function up(): void
    {
        Schema::create('master_role', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('level');
            $table->boolean('is_superadmin')->default(false); // bypass semua hak akses
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_role');
    }
};
