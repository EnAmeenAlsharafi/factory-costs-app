<?php

namespace App\Domain\Reports;

/**
 * One column of a management report. The same definition renders the screen table and the CSV export,
 * so an export can never contain a column the user is not allowed to see on screen.
 */
final class ReportColumn
{
    public const TEXT = 'text';

    public const CODE = 'code';

    public const MONEY = 'money';

    public const QTY = 'qty';

    public const INT = 'int';

    public const PERCENT = 'percent';

    public const DATE = 'date';

    public const LINK = 'link';

    public const BADGE = 'badge';

    /**
     * @param  list<string>  $abilities  All abilities required to see this column (e.g. ['costing.view']).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TEXT,
        public readonly array $abilities = [],
        public readonly bool $highlightNegative = false,
        public readonly ?string $hint = null,
    ) {}

    public static function make(string $key, string $label, string $type = self::TEXT): self
    {
        return new self($key, $label, $type);
    }

    /**
     * @param  list<string>  $abilities
     */
    public function requires(array $abilities): self
    {
        return new self($this->key, $this->label, $this->type, [...$this->abilities, ...$abilities], $this->highlightNegative, $this->hint);
    }

    public function negativeAlert(): self
    {
        return new self($this->key, $this->label, $this->type, $this->abilities, true, $this->hint);
    }

    public function withHint(string $hint): self
    {
        return new self($this->key, $this->label, $this->type, $this->abilities, $this->highlightNegative, $hint);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, [self::MONEY, self::QTY, self::INT, self::PERCENT], true);
    }

    /**
     * Screen representation. Null means "not available / not calculated" and is shown as an em dash, never as zero.
     */
    public function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($this->type) {
            self::MONEY => ReportFormat::money($value),
            self::QTY => ReportFormat::quantity($value),
            self::INT => number_format((int) $value),
            self::PERCENT => ReportFormat::percent($value),
            self::LINK => (string) ($value['text'] ?? '—'),
            self::BADGE => (string) ($value['label'] ?? '—'),
            default => (string) $value,
        };
    }

    /**
     * Machine-readable CSV value (no thousands separators or currency symbols; empty when not available).
     */
    public function export(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->type) {
            self::MONEY => number_format((float) $value, 2, '.', ''),
            self::QTY => ReportFormat::quantity($value, false),
            self::INT => (string) (int) $value,
            self::PERCENT => number_format((float) $value, 1, '.', ''),
            self::LINK => (string) ($value['text'] ?? ''),
            self::BADGE => (string) ($value['label'] ?? ''),
            default => (string) $value,
        };
    }
}
