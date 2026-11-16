<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Level 2 hierarki lokasi: template fisik (jenis rak, dimensi, kapasitas, aturan mixing).
     * Contoh: "Rak pallet 1.2x1.0x1.5m maks 1 ton" di bawah tipe Storage.
     * Lokasi dengan ukuran/aturan berbeda = class berbeda.
     */
    public function up(): void
    {
        Schema::create('master_location_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_type_id')->constrained('master_location_type')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            // selective_rack, drive_in, floor, shelving, mezzanine, flow_rack, ...
            $table->string('storage_type', 30)->nullable();

            // Dimensi dalam (cm)
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();

            // Kapasitas (null = tidak dibatasi)
            $table->decimal('max_weight_kg', 12, 3)->nullable();
            $table->decimal('max_volume_m3', 12, 6)->nullable();
            $table->unsignedSmallInteger('max_lpn')->nullable(); // jumlah pallet/LPN

            // Kebijakan mixing
            $table->boolean('allow_mixed_items')->default(true);
            $table->boolean('allow_mixed_lots')->default(true);

            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_location_class');
    }
};
