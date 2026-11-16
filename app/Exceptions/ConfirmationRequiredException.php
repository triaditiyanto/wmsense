<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Transaksi boleh lanjut, tapi butuh konfirmasi user dulu (mis. qty melebihi ASN).
 * Dirender sebagai HTTP 409 + daftar peringatan; klien mengirim ulang dengan confirm=1.
 */
class ConfirmationRequiredException extends RuntimeException
{
    /** @param list<string> $warnings */
    public function __construct(public readonly array $warnings, public readonly bool $canConfirm)
    {
        parent::__construct(implode(' ', $warnings));
    }
}
