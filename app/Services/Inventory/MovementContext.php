<?php

namespace App\Services\Inventory;

use Illuminate\Database\Eloquent\Model;

/** Keterangan "kenapa" stok berubah: dokumen sumber, user, idempotency key, alasan. */
final readonly class MovementContext
{
    public function __construct(
        public ?Model $reference = null,     // mis. ReceiptHeader
        public ?int $userId = null,
        public ?string $idempotencyKey = null,
        public int $seq = 1,
        public ?string $reasonCode = null,
        public ?string $notes = null,
    ) {
    }
}
