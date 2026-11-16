<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task gudang (header/detail). Saat ini dipakai untuk PUTAWAY (pindah satu LPN dari dock ke rak);
 * nanti juga REPLENISH / MOVE / PICK.
 *
 * Satu task PUTAWAY = satu LPN. Dibuat saat operator memulai (scan LPN / klik Putaway) dengan status
 * IN_PROGRESS dan lokasi saran -> lokasi saran "dipesan" (dihitung terisi) supaya dua operator tidak
 * diarahkan ke slot yang sama. Selesai = seluruh stok LPN dipindah, isi yang dipindah dicatat di detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('o_task_header', function (Blueprint $table) {
            $table->id();
            $table->string('task_no', 30)->unique();
            $table->string('task_type', 20);                     // App\Enums\TaskType
            $table->string('status', 20)->default('IN_PROGRESS'); // App\Enums\TaskStatus
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('master_lpn')->restrictOnDelete();
            $table->foreignId('from_location_id')->constrained('master_location')->restrictOnDelete();
            $table->foreignId('suggested_location_id')->nullable()->constrained('master_location')->nullOnDelete();
            $table->string('suggest_reason')->nullable();
            $table->foreignId('to_location_id')->nullable()->constrained('master_location')->restrictOnDelete();
            $table->string('source', 10)->default('DESKTOP');     // DESKTOP | MOBILE (tempat diselesaikan)

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'task_type', 'status']);
            $table->index(['lpn_id', 'status']);
            $table->index(['suggested_location_id', 'status']);
        });

        Schema::create('o_task_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_header_id')->constrained('o_task_header')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->string('lot_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('inventory_status', 20);               // App\Enums\InventoryStatus
            $table->decimal('qty_base', 18, 4);
            $table->dateTime('received_at')->nullable();          // dibawa ke lokasi tujuan (dasar FIFO)
            $table->timestamps();
        });

        // Baris terima yang sudah disimpan ke rak -> tidak tampil lagi di Intransit In & tidak bisa dibatalkan
        Schema::table('o_receipt_receiving', function (Blueprint $table) {
            $table->foreignId('putaway_task_id')->nullable()->after('cancel_reason')->constrained('o_task_header')->nullOnDelete();
            $table->timestamp('putaway_at')->nullable()->after('putaway_task_id');
            $table->index(['lpn_id', 'putaway_at']);
        });
    }

    public function down(): void
    {
        Schema::table('o_receipt_receiving', function (Blueprint $table) {
            $table->dropConstrainedForeignId('putaway_task_id');
            $table->dropIndex(['lpn_id', 'putaway_at']);
            $table->dropColumn('putaway_at');
        });
        Schema::dropIfExists('o_task_detail');
        Schema::dropIfExists('o_task_header');
    }
};
