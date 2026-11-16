<?php

namespace App\Support;

/**
 * Helper qty. Kolom decimal dibaca Laravel sebagai string ("12.5000"),
 * jadi aritmetika memakai bcmath agar tidak ada error pembulatan float.
 */
class Qty
{
    public const SCALE = 4;

    public static function mul(string|int|float $a, string|int|float $b): string
    {
        return bcmul((string) $a, (string) $b, self::SCALE);
    }

    public static function add(string|int|float $a, string|int|float $b): string
    {
        return bcadd((string) $a, (string) $b, self::SCALE);
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        return bcsub((string) $a, (string) $b, self::SCALE);
    }

    public static function cmp(string|int|float $a, string|int|float $b): int
    {
        return bccomp((string) $a, (string) $b, self::SCALE);
    }

    public static function isWhole(string|int|float $a): bool
    {
        return bccomp((string) $a, bcadd((string) $a, '0', 0), self::SCALE) === 0;
    }

    /** Untuk tampilan: "1200.5000" -> "1.200,5" */
    public static function fmt(string|int|float|null $a): string
    {
        if ($a === null || $a === '') {
            return '-';
        }
        $s = rtrim(rtrim(number_format((float) $a, self::SCALE, ',', '.'), '0'), ',');

        return $s === '-0' ? '0' : $s;
    }
}
