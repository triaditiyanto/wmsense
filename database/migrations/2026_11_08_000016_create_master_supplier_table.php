<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supplier / vendor pengirim barang (inbound PURCHASE, nanti juga retur ke supplier di outbound).
     * owner_id null = bisa dipakai semua owner; terisi = khusus owner tsb (3PL).
     */
    public function up(): void
    {
        Schema::create('master_supplier', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('owner_id')->nullable()->constrained('master_owner')->restrictOnDelete();
            $table->string('tax_id', 30)->nullable();   // NPWP
            $table->string('pic', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['owner_id', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_supplier');
    }
};
