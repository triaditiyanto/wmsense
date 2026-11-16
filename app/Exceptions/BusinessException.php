<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (status tidak boleh berubah, dokumen kosong, dst).
 * Dirender di bootstrap/app.php: JSON 422 untuk request AJAX, flash "error" untuk request biasa.
 */
class BusinessException extends RuntimeException
{
}
