<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->foreignId('item_class_id')->constrained('master_item_class')->restrictOnDelete();
            $table->foreignId('base_uom_id')->constrained('master_uom')->restrictOnDelete();
            $table->string('sku', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('barcode', 64)->nullable();

            $table->string('rotation_rule', 10)->default('FIFO');
            $table->string('temperature_class', 20)->default('AMBIENT');
            $table->boolean('is_lot_tracked')->default(false);
            $table->boolean('is_expiry_tracked')->default(false);
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->unsignedInteger('min_shelf_life_days')->nullable(); // batas terima inbound
            $table->boolean('is_hazardous')->default(false);

            // Dimensi per 1 base UOM
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();

            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['owner_id', 'sku']);
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_item');
    }
};
