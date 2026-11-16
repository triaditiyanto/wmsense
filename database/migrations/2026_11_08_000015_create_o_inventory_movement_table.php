<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger append-only. Satu baris = satu perubahan pada satu baris inventory.
     * Atribut stok disalin (snapshot) agar histori tetap terbaca walau master berubah.
     */
    public function up(): void
    {
        Schema::create('o_inventory_movement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained('o_inventory')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('master_location')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('lpn_id')->nullable()->constrained('master_lpn')->restrictOnDelete();
            $table->string('status', 20);

            $table->string('type', 20);
            $table->decimal('qty_on_hand_change', 18, 4)->default(0);
            $table->decimal('qty_allocated_change', 18, 4)->default(0);
            $table->decimal('qty_hold_change', 18, 4)->default(0);
            // saldo sesudah movement, memudahkan audit & rekonsiliasi
            $table->decimal('qty_on_hand_after', 18, 4);
            $table->decimal('qty_allocated_after', 18, 4);
            $table->decimal('qty_hold_after', 18, 4);

            // dokumen sumber (receipt, task, order, adjustment, ...)
            $table->nullableMorphs('reference');
            $table->string('reason_code', 30)->nullable();
            $table->text('notes')->nullable();

            // Satu transaksi scanner bisa menulis beberapa movement (OUT + IN),
            // jadi keunikannya pada pasangan (idempotency_key, seq).
            $table->string('idempotency_key', 100)->nullable();
            $table->unsignedSmallInteger('seq')->default(1);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['idempotency_key', 'seq']);
            $table->index(['inventory_id', 'id']);
            $table->index(['item_id', 'created_at']);
            $table->index(['warehouse_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_inventory_movement');
    }
};
