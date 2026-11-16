<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel users bawaan Laravel dipakai sebagai master user (tidak di-rename agar
     * fitur auth bawaan tetap jalan). Login memakai username, email opsional.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->foreignId('role_id')->after('password')->constrained('master_role')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['username', 'is_active', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
