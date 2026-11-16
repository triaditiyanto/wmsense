<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat penerimaan: satu baris = satu kali "Terima" (scan) di desktop / mobile.
     * Stoknya masuk ke o_inventory (lokasi dock + LPN) lewat InventoryService.
     * Baris tidak dihapus; salah scan dibatalkan (cancelled_at) dan stoknya dibalik lewat ledger.
     */
    public function up(): void
    {
        Schema::create('o_receipt_receiving', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_header_id')->constrained('o_receipt_header')->restrictOnDelete();
            $table->foreignId('receipt_detail_id')->constrained('o_receipt_detail')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('master_location')->restrictOnDelete(); // dock
            $table->foreignId('lpn_id')->constrained('master_lpn')->restrictOnDelete();

            $table->foreignId('uom_id')->constrained('master_uom')->restrictOnDelete();
            $table->decimal('qty', 18, 4);                 // sesuai UOM input
            $table->decimal('conversion_factor', 18, 6);
            $table->decimal('qty_base', 18, 4);            // dalam base UOM

            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('condition', 20)->default('GOOD'); // App\Enums\ReceivingCondition

            $table->string('source', 10)->default('DESKTOP'); // DESKTOP | MOBILE
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->useCurrent();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['receipt_header_id', 'cancelled_at']);
            $table->index('lpn_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_receipt_receiving');
    }
};
