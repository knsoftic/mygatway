<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Fixed-precision decimal arithmetic for money, backed by bcmath.
 *
 * Amounts are always passed around as numeric strings and stored as
 * DECIMAL(30,8). Floats are never used for money calculations.
 */
final class Decimal
{
    public const SCALE = 8;

    /** Intermediate precision used before the final rounding step. */
    private const WORK_SCALE = 18;

    private const PATTERN = '/^-?\d{1,22}(\.\d+)?$/';

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Convert provider/user input to a canonical 8-decimal string.
     * Floats (e.g. Binance JSON numbers) are formatted with fixed notation first.
     */
    public static function of(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Amount is required.');
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Amount must be finite.');
            }
            $value = sprintf('%.'.self::SCALE.'F', $value);
        }

        $value = trim((string) $value);

        if (! self::isValid($value)) {
            throw new InvalidArgumentException("Invalid decimal amount [{$value}].");
        }

        return self::round($value);
    }

    public static function ofOrNull(string|int|float|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::of($value);
    }

    public static function zero(): string
    {
        return self::round('0');
    }

    public static function add(string $a, string $b): string
    {
        return self::round(bcadd($a, $b, self::WORK_SCALE));
    }

    public static function sub(string $a, string $b): string
    {
        return self::round(bcsub($a, $b, self::WORK_SCALE));
    }

    public static function mul(string $a, string $b): string
    {
        return self::round(bcmul($a, $b, self::WORK_SCALE));
    }

    public static function div(string $a, string $b): string
    {
        if (self::isZero($b)) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return self::round(bcdiv($a, $b, self::WORK_SCALE));
    }

    /** amount * percentage / 100, rounded half-up once at the end. */
    public static function percentOf(string $amount, string $percentage): string
    {
        return self::round(bcdiv(bcmul($amount, $percentage, self::WORK_SCALE), '100', self::WORK_SCALE));
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, self::WORK_SCALE);
    }

    public static function eq(string $a, string $b): bool
    {
        return self::cmp($a, $b) === 0;
    }

    public static function gt(string $a, string $b): bool
    {
        return self::cmp($a, $b) === 1;
    }

    public static function gte(string $a, string $b): bool
    {
        return self::cmp($a, $b) >= 0;
    }

    public static function lt(string $a, string $b): bool
    {
        return self::cmp($a, $b) === -1;
    }

    public static function lte(string $a, string $b): bool
    {
        return self::cmp($a, $b) <= 0;
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') === 1;
    }

    public static function isNegative(string $a): bool
    {
        return self::cmp($a, '0') === -1;
    }

    public static function max(string $a, string $b): string
    {
        return self::gte($a, $b) ? self::round($a) : self::round($b);
    }

    public static function min(string $a, string $b): string
    {
        return self::lte($a, $b) ? self::round($a) : self::round($b);
    }

    public static function negate(string $a): string
    {
        return self::round(bcmul($a, '-1', self::WORK_SCALE));
    }

    /** Round half away from zero to the given scale. */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $increment = '0.'.str_repeat('0', $scale).'5';
        $rounded = str_starts_with($value, '-')
            ? bcsub($value, $increment, $scale)
            : bcadd($value, $increment, $scale);

        // Normalise negative zero.
        return bccomp($rounded, '0', $scale) === 0 ? bcadd('0', '0', $scale) : $rounded;
    }

    /** Human display: trims trailing zeros but keeps at least two decimals. */
    public static function format(?string $value, int $minDecimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $value = self::round($value);
        [$int, $frac] = array_pad(explode('.', $value), 2, '');
        $negative = str_starts_with($int, '-');
        $int = ltrim($int, '-');
        $frac = rtrim($frac, '0');
        $frac = str_pad($frac, $minDecimals, '0');

        // Group thousands on the string itself so large values never pass through a float.
        $grouped = strrev(implode(',', str_split(strrev($int), 3)));

        return ($negative ? '-' : '').$grouped.($frac !== '' ? '.'.$frac : '');
    }

    /** Plain decimal string for provider APIs (no trailing zeros, no exponent). */
    public static function plain(string $value): string
    {
        $value = self::round($value);
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' || $value === '-0' ? '0' : $value;
    }
}
