<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penomoran dokumen otomatis (RCV-WH1-2611-0001). Satu baris per
     * jenis dokumen + warehouse + periode; dikunci (lockForUpdate) saat ambil nomor.
     */
    public function up(): void
    {
        Schema::create('o_document_sequence', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 10);      // RCV, ORD, ...
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->string('period', 4);         // YYMM
            $table->unsignedInteger('last_no')->default(0);
            $table->timestamps();

            $table->unique(['doc_type', 'warehouse_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_document_sequence');
    }
};
