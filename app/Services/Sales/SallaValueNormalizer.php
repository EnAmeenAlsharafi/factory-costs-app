<?php

namespace App\Services\Sales;

/**
 * Stateless value-cleanup helpers shared by the Salla Excel import parser
 * and the sales_item_mappings match_value, so a mapping saved with one
 * normalization is found by the same normalization at import time.
 */
class SallaValueNormalizer
{
    private const PLACEHOLDER_VALUES = ['\\N', 'NA', 'N/A', '-', ''];

    /**
     * Convert Arabic-Indic digits to their Latin equivalents.
     */
    public static function toLatinDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicIndic, $latin, $value);
    }

    /**
     * Strip a single layer of wrapping single quotes, e.g. 'سدير' -> سدير.
     */
    public static function stripQuotes(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if (strlen($trimmed) >= 2 && str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'")) {
            return substr($trimmed, 1, -1);
        }

        return $trimmed;
    }

    /**
     * Collapse Salla's various "no value" placeholders to null.
     */
    public static function nullIfPlaceholder(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = self::stripQuotes($value);
        $comparable = strtoupper(trim($value ?? ''));

        return in_array($comparable, self::PLACEHOLDER_VALUES, true) ? null : $value;
    }

    /**
     * Normalize a Saudi mobile number to the canonical local format used
     * throughout this system (05XXXXXXXX), regardless of whether Salla sent
     * it as +9665XXXXXXXX, 9665XXXXXXXX, or 05XXXXXXXX.
     */
    public static function normalizeMobile(?string $value): ?string
    {
        $value = self::nullIfPlaceholder($value);
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', self::toLatinDigits($value));

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '966')) {
            $digits = '0'.substr($digits, 3);
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * Normalize free text for matching: trim, collapse whitespace, unify
     * common Arabic letter variants and digits, lowercase Latin characters.
     */
    public static function normalizeMatchValue(?string $value): ?string
    {
        $value = self::nullIfPlaceholder($value);
        if ($value === null) {
            return null;
        }

        $value = self::toLatinDigits($value);
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $value) ?? $value;
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
