<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Level 2 hierarki item, di bawah item group. Contoh: Makanan → Snack, Frozen Food.
     */
    public function up(): void
    {
        Schema::create('master_item_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_group_id')->constrained('master_item_group')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_item_class');
    }
};
