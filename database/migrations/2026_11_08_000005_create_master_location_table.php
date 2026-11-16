<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Level 3 hierarki lokasi: lokasi fisik (bin). Tipe & kapasitas diwarisi dari class.
     */
    public function up(): void
    {
        Schema::create('master_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->foreignId('zone_id')->constrained('master_zone')->restrictOnDelete();
            $table->foreignId('location_class_id')->constrained('master_location_class')->restrictOnDelete();
            $table->string('code', 40); // = barcode label lokasi
            $table->string('status', 20)->default('ACTIVE');

            // Koordinat fisik
            $table->string('aisle', 10)->nullable();
            $table->string('bay', 10)->nullable();
            $table->unsignedSmallInteger('level')->nullable(); // 1 = paling bawah
            $table->string('position', 10)->nullable();
            $table->unsignedInteger('pick_sequence')->nullable(); // urutan jalur picking

            $table->string('temperature_class', 20)->nullable(); // null = ikut zona
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['zone_id', 'status']);
            $table->index(['location_class_id', 'status']);
            $table->index(['warehouse_id', 'pick_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_location');
    }
};
