<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'SUPERADMIN', 'name' => 'Superadmin', 'level' => 0, 'is_superadmin' => true, 'description' => 'Pengelola sistem, akses penuh.'],
            ['code' => 'MANAGER', 'name' => 'Manager', 'level' => 1, 'is_superadmin' => false, 'description' => 'Manager gudang.'],
            ['code' => 'SPV', 'name' => 'Supervisor', 'level' => 2, 'is_superadmin' => false, 'description' => 'Supervisor operasional.'],
            ['code' => 'ADMIN', 'name' => 'Admin', 'level' => 3, 'is_superadmin' => false, 'description' => 'Admin dokumen (input receipt/order).'],
            ['code' => 'OPERATOR', 'name' => 'Operator', 'level' => 4, 'is_superadmin' => false, 'description' => 'Operator lapangan (scanner).'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code' => $role['code']], $role);
        }
    }
}
