<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/** User contoh untuk development. Semua password: "password" — ganti sebelum production! */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'superadmin', 'name' => 'Super Admin', 'role' => 'SUPERADMIN'],
            ['username' => 'manager', 'name' => 'Budi Manager', 'role' => 'MANAGER'],
            ['username' => 'spv', 'name' => 'Sari Supervisor', 'role' => 'SPV'],
            ['username' => 'admin', 'name' => 'Andi Admin', 'role' => 'ADMIN'],
            ['username' => 'operator', 'name' => 'Oki Operator', 'role' => 'OPERATOR'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['username' => $u['username']], [
                'name' => $u['name'],
                'password' => 'password', // di-hash otomatis oleh cast 'hashed'
                'role_id' => Role::where('code', $u['role'])->value('id'),
                'is_active' => true,
            ]);
        }
    }
}
