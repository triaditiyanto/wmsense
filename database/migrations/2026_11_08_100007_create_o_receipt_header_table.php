<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header dokumen penerimaan barang (ASN / receipt).
     */
    public function up(): void
    {
        Schema::create('o_receipt_header', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained('master_warehouse')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('master_owner')->restrictOnDelete();
            $table->string('receipt_type', 20);          // App\Enums\ReceiptType
            $table->string('status', 20)->default('DRAFT'); // App\Enums\ReceiptStatus
            $table->string('reference_no', 50)->nullable(); // no. PO / DO / surat jalan
            // Pengirim: supplier (PURCHASE/TRANSFER/OTHER) atau customer (RETURN)
            $table->foreignId('supplier_id')->nullable()->constrained('master_supplier')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('master_customer')->restrictOnDelete();
            $table->date('expected_date')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['owner_id', 'status']);
            $table->index('reference_no');
            $table->index('expected_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('o_receipt_header');
    }
};
