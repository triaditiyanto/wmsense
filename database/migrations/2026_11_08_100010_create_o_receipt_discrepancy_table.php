<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selisih penerimaan (kurang / lebih / rusak), dibuat otomatis saat "Selesai Terima".
     */
    public function up(): void
    {
        Schema::create('o_receipt_discrepancy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_header_id')->constrained('o_receipt_header')->cascadeOnDelete();
            $table->foreignId('receipt_detail_id')->constrained('o_receipt_detail')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();
            $table->string('type', 20);                       // App\Enums\DiscrepancyType
            $table->decimal('qty_expected_base', 18, 4);
            $table->decimal('qty_received_base', 18, 4);
            $table->decimal('qty_diff_base', 18, 4);          // + lebih, - kurang, (rusak: qty rusak)
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['receipt_header_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_receipt_discrepancy');
    }
};
