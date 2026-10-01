<?php

namespace App\Domain\Reports;

final class ReportFormat
{
    /**
     * Left-to-right mark placed before a negative number so the minus sign stays with the digits in RTL text
     * (otherwise "-242.22" renders as "242.22-").
     */
    private const LRM = "\u{200E}";

    public static function money(mixed $value): string
    {
        return self::signed((float) $value, number_format((float) $value, 2)).' ر.س';
    }

    /**
     * Quantities keep up to three meaningful decimals (fabric metres) and drop trailing zeros (piece units).
     */
    public static function quantity(mixed $value, bool $grouped = true): string
    {
        $formatted = number_format((float) $value, 3, '.', $grouped ? ',' : '');
        $formatted = str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;

        return $grouped ? self::signed((float) $value, $formatted) : $formatted;
    }

    public static function percent(mixed $value): string
    {
        return self::signed((float) $value, number_format((float) $value, 1)).'%';
    }

    private static function signed(float $value, string $formatted): string
    {
        return $value < 0 ? self::LRM.$formatted : $formatted;
    }

    /**
     * Ratio as a percentage, or null when the denominator is zero (never a misleading 0%).
     */
    public static function ratio(float|int|string|null $numerator, float|int|string|null $denominator): ?float
    {
        $denominator = (float) $denominator;

        if ($numerator === null || abs($denominator) < 0.00001) {
            return null;
        }

        return round(((float) $numerator / $denominator) * 100, 1);
    }
}
