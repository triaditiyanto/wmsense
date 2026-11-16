<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Level 1 hierarki lokasi: tipe fungsional (Storage, Pick Face, Receiving Dock, ...).
     * Nama & kode bebas diatur user; perilaku sistem ditentukan oleh location_function (enum).
     */
    public function up(): void
    {
        Schema::create('master_location_type', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('location_function', 20); // App\Enums\LocationFunction
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_location_type');
    }
};
