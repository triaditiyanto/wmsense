<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kapasitas per lokasi (override). NULL = ikut kapasitas di kelas lokasi (master_location_class).
     * Dipakai untuk saran dock saat receiving, locating (putaway) dan replenishment.
     */
    public function up(): void
    {
        Schema::table('master_location', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_lpn')->nullable()->after('pick_sequence');
            $table->decimal('max_weight_kg', 12, 3)->nullable()->after('max_lpn');
            $table->decimal('max_volume_m3', 12, 6)->nullable()->after('max_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('master_location', function (Blueprint $table) {
            $table->dropColumn(['max_lpn', 'max_weight_kg', 'max_volume_m3']);
        });
    }
};
