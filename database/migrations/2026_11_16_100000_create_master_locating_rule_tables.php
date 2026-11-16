<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan locating (Master > Item Locating): untuk Item / Item Class / Item Group di satu gudang,
 * ke area mana barang disimpan saat putaway, berurutan prioritas.
 *   master_locating_rule        : header per scope (paling spesifik menang: Item > Item Class > Item Group)
 *   master_locating_rule_target : area tujuan berurutan (zona / aisle / kelas lokasi / lokasi) + rentang level
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_locating_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->string('scope_type', 20);               // ITEM_GROUP | ITEM_CLASS | ITEM (LocatingScope)
            $table->unsignedBigInteger('scope_id');         // id item_group / item_class / item
            $table->string('level_preference', 10)->nullable(); // LOW | HIGH | null = bebas
            $table->boolean('prefer_consolidate')->default(true); // dahulukan lokasi berisi item sama
            $table->boolean('strict')->default(false);      // true = hanya area di aturan (di luar = butuh SPV)
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['warehouse_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('master_locating_rule_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locating_rule_id')->constrained('master_locating_rule')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');            // 1 = prioritas tertinggi
            $table->string('target_type', 20);              // ZONE | AISLE | LOCATION_CLASS | LOCATION
            $table->foreignId('zone_id')->nullable()->constrained('master_zone')->restrictOnDelete();
            $table->string('aisle', 10)->nullable();        // AISLE: zone_id + aisle
            $table->foreignId('location_class_id')->nullable()->constrained('master_location_class')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('master_location')->restrictOnDelete();
            $table->unsignedSmallInteger('level_min')->nullable();
            $table->unsignedSmallInteger('level_max')->nullable();
            $table->timestamps();

            $table->index(['locating_rule_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_locating_rule_target');
        Schema::dropIfExists('master_locating_rule');
    }
};
