<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail dokumen penerimaan: item apa saja yang diharapkan datang.
     * Qty disimpan dua bentuk: sesuai UOM input (untuk tampilan) dan base UOM (acuan sistem).
     */
    public function up(): void
    {
        Schema::create('o_receipt_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_header_id')->constrained('o_receipt_header')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('item_id')->constrained('master_item')->restrictOnDelete();

            // Input user
            $table->foreignId('uom_id')->constrained('master_uom')->restrictOnDelete();
            $table->decimal('qty_expected', 18, 4);
            $table->decimal('conversion_factor', 18, 6); // snapshot saat input

            // Dalam base UOM item
            $table->decimal('qty_expected_base', 18, 4);
            $table->decimal('qty_received_base', 18, 4)->default(0);   // total diterima (baik + rusak)
            $table->decimal('qty_damaged_base', 18, 4)->default(0);    // bagian yang rusak

            $table->string('lot_no', 50)->nullable();     // bila sudah diketahui dari supplier
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['receipt_header_id', 'line_no']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_receipt_detail');
    }
};
