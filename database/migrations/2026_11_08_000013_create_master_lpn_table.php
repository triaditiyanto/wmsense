<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LPN (License Plate Number) = "lokasi bergerak": label barcode yang ditempel di
     * pallet/karton/tote dan dipakai dari inbound sampai outbound.
     * Didaftarkan lewat master (manual / generate) atau otomatis saat receiving.
     * Stok di dalam LPN: o_inventory.lpn_id; posisi LPN: location_id.
     */
    public function up(): void
    {
        Schema::create('master_lpn', function (Blueprint $table) {
            $table->id();
            $table->string('lpn_no', 50)->unique();          // isi barcode
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->string('lpn_type', 20)->default('PALLET'); // App\Enums\LpnType
            $table->foreignId('pallet_id')->nullable()->constrained('master_pallet')->restrictOnDelete();
            $table->string('status', 20)->default('EMPTY');  // App\Enums\LpnStatus
            $table->foreignId('location_id')->nullable()->constrained('master_location')->restrictOnDelete();
            $table->string('registered_via', 20)->default('MASTER'); // MASTER | RECEIVING
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_lpn');
    }
};
