<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jenis / spesifikasi pallet (mis. "Pallet Kayu 120x100", "Pallet Plastik Euro").
     * Dipakai LPN bertipe PALLET untuk menghitung berat & tinggi total.
     */
    public function up(): void
    {
        Schema::create('master_pallet', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('material', 20)->default('WOOD'); // App\Enums\PalletMaterial
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();     // tinggi pallet kosong
            $table->decimal('tare_weight_kg', 10, 3)->nullable(); // berat pallet kosong
            $table->decimal('max_load_kg', 12, 3)->nullable();
            $table->decimal('max_stack_height_cm', 10, 2)->nullable(); // tinggi maks pallet + barang
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_pallet');
    }
};
