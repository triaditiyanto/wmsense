<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner = pemilik barang. Gudang non-3PL cukup punya satu owner default,
     * sehingga item & inventory selalu punya owner_id (tidak ada NULL).
     */
    public function up(): void
    {
        Schema::create('master_owner', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->json('contact')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_owner');
    }
};
