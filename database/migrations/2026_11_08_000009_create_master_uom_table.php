<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_uom', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // PCS, BOX, CTN, PLT, KG, ...
            $table->string('name');
            $table->boolean('allow_decimal')->default(false); // KG/LTR = true
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_uom');
    }
};
