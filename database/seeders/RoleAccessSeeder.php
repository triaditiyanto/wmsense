<?php

namespace Database\Seeders;

use App\Models\ModuleDetail;
use App\Models\Role;
use App\Models\RoleAccess;
use Illuminate\Database\Seeder;

/**
 * Hak akses default per role. Setelah itu diatur lewat menu Master > Role & Hak Akses.
 * Huruf: V=view C=create E=edit D=delete A=approve. '*' = semua module detail.
 */
class RoleAccessSeeder extends Seeder
{
    /** Bila diisi: hanya module detail dengan kode ini yang diberi hak akses default (dipakai portal:upgrade). */
    public ?array $only = null;

    public function run(): void
    {
        $matrix = [
            'MANAGER' => ['*' => 'VCEDA'],
            'SPV' => ['*' => 'VCEDA', 'MST_ROLE' => 'V', 'MST_USER' => 'VCE'],
            'ADMIN' => ['*' => 'V', 'MST_USER' => '', 'MST_ROLE' => '', 'INB_RECEIPT' => 'VCEDA', 'INB_RECEIVING' => 'VC', 'INB_INTRANSIT' => 'V',
                'OUT_SHIPMENT' => 'VCEDA', 'OUT_WAVE' => 'VCE', 'OUT_DISPATCH' => 'VCED', 'OUT_RPT_DETAIL' => 'V', 'OUT_RPT_SUMMARY' => 'V',
                'INV_MOVE' => 'VC', 'INV_ADJUST' => 'V', 'INV_HOLD' => 'V'],
            'OPERATOR' => ['INB_RECEIPT' => 'V', 'INB_RECEIVING' => 'VC', 'INB_PUTAWAY' => 'VC', 'OUT_PICKING' => 'VC', 'INV_STOCK' => 'V', 'INV_MAP3D' => 'V'],
        ];
        $details = ModuleDetail::all();

        foreach ($matrix as $roleCode => $rules) {
            $role = Role::where('code', $roleCode)->firstOrFail();

            foreach ($details as $detail) {
                if ($this->only !== null && ! in_array($detail->code, $this->only, true)) {
                    continue;
                }
                $flags = $rules[$detail->code] ?? $rules['*'] ?? '';
                RoleAccess::updateOrCreate(
                    ['role_id' => $role->id, 'module_detail_id' => $detail->id],
                    [
                        'can_view' => str_contains($flags, 'V'),
                        'can_create' => str_contains($flags, 'C'),
                        'can_edit' => str_contains($flags, 'E'),
                        'can_delete' => str_contains($flags, 'D'),
                        'can_approve' => str_contains($flags, 'A'),
                    ],
                );
            }
        }
    }
}
