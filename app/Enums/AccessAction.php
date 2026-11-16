<?php

namespace App\Enums;

/** Aksi dalam matriks hak akses; tiap aksi = satu kolom can_* di master_role_access. */
enum AccessAction: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case EDIT = 'edit';
    case DELETE = 'delete';
    case APPROVE = 'approve';

    public function column(): string
    {
        return 'can_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'Lihat',
            self::CREATE => 'Tambah',
            self::EDIT => 'Ubah',
            self::DELETE => 'Hapus',
            self::APPROVE => 'Approve',
        };
    }
}
