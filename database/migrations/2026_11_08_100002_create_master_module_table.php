<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module = grup menu (header). Contoh: Master Data, Inbound, Outbound.
     */
    public function up(): void
    {
        Schema::create('master_module', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('icon', 50)->nullable(); // nama Bootstrap Icon, mis. "box-arrow-in-down"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_module');
    }
};
