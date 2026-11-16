<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konversi UOM per item. conversion_factor = jumlah base UOM dalam 1 UOM ini
     * (mis. 1 CTN = 24 PCS). Baris untuk base UOM sendiri memakai factor 1.
     */
    public function up(): void
    {
        Schema::create('master_item_uom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('master_item')->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('master_uom')->restrictOnDelete();
            $table->decimal('conversion_factor', 18, 6);
            $table->string('barcode', 64)->nullable();
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->boolean('is_default_inbound')->default(false);
            $table->boolean('is_default_outbound')->default(false);
            $table->timestamps();

            $table->unique(['item_id', 'uom_id']);
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_item_uom');
    }
};
