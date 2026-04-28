<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal-safe quantity helpers (bcmath).
 */
final class InventoryMath
{
    private const SCALE = 6;

    public static function normalize(string|int|float $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        $s = (string) $value;

        if (trim($s) === '' || ! is_numeric($s)) {
            throw new InvalidArgumentException('Quantity must be a numeric value.');
        }

        if (function_exists('bcadd')) {
            return bcadd($s, '0', self::SCALE);
        }

        return number_format((float) $s, self::SCALE, '.', '');
    }

    public static function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
        }

        $sum = (float) self::normalize($a) + (float) self::normalize($b);

        return number_format($sum, self::SCALE, '.', '');
    }

    public static function sub(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
        }

        $diff = (float) self::normalize($a) - (float) self::normalize($b);

        return number_format($diff, self::SCALE, '.', '');
    }

    public static function multiply(string|int|float $a, string|int|float $b): string
    {
        $x = self::normalize($a);
        $y = self::normalize($b);

        if (function_exists('bcmul')) {
            return bcmul($x, $y, self::SCALE);
        }

        $product = (float) $x * (float) $y;

        return number_format($product, self::SCALE, '.', '');
    }

    /**
     * @return int<-1, 1>
     */
    public static function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
        }

        $x = (float) self::normalize($a);
        $y = (float) self::normalize($b);

        return $x <=> $y;
    }

    public static function isNegative(string $value): bool
    {
        return self::compare($value, '0') < 0;
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }
}
