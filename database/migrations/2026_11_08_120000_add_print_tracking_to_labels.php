<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak cetak label: berapa kali & kapan terakhir label LPN / lokasi dicetak. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['master_lpn', 'master_location'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('print_count')->default(0);
                $t->dateTime('last_printed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['master_lpn', 'master_location'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['print_count', 'last_printed_at']));
        }
    }
};
