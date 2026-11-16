<?php

namespace App\Exceptions;

use LogicException;

/** Dilempar saat ada upaya mengubah/menghapus record append-only (ledger). */
class ImmutableRecordException extends LogicException
{
}
