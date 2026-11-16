<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo stok per lokasi + atribut stok. Hanya boleh diubah lewat InventoryService.
     * Semua qty dalam base UOM item.
     */
    public function up(): void
    {
        Schema::create('o_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('master_location')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            // LPN = lokasi bergerak (pallet/karton). Bila terisi, location_id stok = location_id LPN.
            $table->foreignId('lpn_id')->nullable()->constrained('master_lpn')->restrictOnDelete();
            $table->string('status', 20)->default('AVAILABLE');

            // sha256(item|owner|lot|exp|lpn_id|status), diisi otomatis oleh model
            $table->char('stock_key', 64);

            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->decimal('qty_allocated', 18, 4)->default(0);
            $table->decimal('qty_hold', 18, 4)->default(0);
            // generated column: dihitung DB, tidak bisa diisi manual
            $table->decimal('qty_available', 18, 4)
                ->storedAs('qty_on_hand - qty_allocated - qty_hold');
            // reservasi kapasitas dari putaway task yang belum selesai
            $table->decimal('qty_incoming', 18, 4)->default(0);

            $table->dateTime('received_at')->nullable(); // dasar FIFO/LIFO
            $table->timestamps();

            $table->unique(['location_id', 'stock_key']);
            $table->index(['item_id', 'owner_id', 'status']);
            $table->index(['warehouse_id', 'item_id']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_inventory');
    }
};
