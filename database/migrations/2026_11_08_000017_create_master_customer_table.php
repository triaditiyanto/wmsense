<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer / penerima barang (outbound order, inbound RETURN).
     * Header = data customer (alamat penagihan), detail = alamat kirim (master_customer_address).
     * owner_id null = bisa dipakai semua owner; terisi = khusus owner tsb (3PL).
     */
    public function up(): void
    {
        Schema::create('master_customer', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('owner_id')->nullable()->constrained('master_owner')->restrictOnDelete();
            $table->string('tax_id', 30)->nullable();   // NPWP
            $table->string('pic', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();        // alamat penagihan / kantor
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['owner_id', 'is_active']);
            $table->index('name');
        });

        Schema::create('master_customer_address', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('master_customer')->cascadeOnDelete();
            $table->string('label', 50);                // mis. "Gudang Bekasi", "Toko Pusat"
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('pic', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_customer_address');
        Schema::dropIfExists('master_customer');
    }
};
