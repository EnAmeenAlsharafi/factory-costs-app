<?php

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A reporting period expressed in the application (business) timezone.
 *
 * Every report states which business date the period applies to (order date, receipt date, ...);
 * the period itself only provides inclusive start/end boundaries.
 */
final class ReportPeriod
{
    /**
     * @var array<string, string>
     */
    public const PRESETS = [
        'today' => 'اليوم',
        'yesterday' => 'أمس',
        'this_week' => 'هذا الأسبوع',
        'this_month' => 'هذا الشهر',
        'last_month' => 'الشهر الماضي',
        'this_year' => 'هذه السنة',
        'custom' => 'فترة مخصصة',
    ];

    private function __construct(
        public readonly string $preset,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request, string $default = 'this_month'): self
    {
        $preset = array_key_exists((string) $request->query('period'), self::PRESETS) ? (string) $request->query('period') : $default;

        if ($preset === 'custom') {
            $from = self::parseDate($request->query('from'));
            $to = self::parseDate($request->query('to'));

            if ($from && $to) {
                [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];

                return new self('custom', $from->startOfDay(), $to->endOfDay());
            }

            $preset = $default;
        }

        return self::preset($preset);
    }

    public static function preset(string $preset): self
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return match ($preset) {
            'today' => new self($preset, $now->startOfDay(), $now->endOfDay()),
            'yesterday' => new self($preset, $now->subDay()->startOfDay(), $now->subDay()->endOfDay()),
            'this_week' => new self($preset, $now->startOfWeek(CarbonImmutable::SATURDAY), $now->endOfDay()),
            'last_month' => new self($preset, $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()),
            'this_year' => new self($preset, $now->startOfYear(), $now->endOfDay()),
            default => new self('this_month', $now->startOfMonth(), $now->endOfDay()),
        };
    }

    public static function between(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self('custom', $start->startOfDay(), $end->endOfDay());
    }

    /**
     * The immediately preceding period of equal length (e.g. this month-to-date vs the same span of last month).
     */
    public function previous(): self
    {
        if ($this->preset === 'last_month') {
            $start = $this->start->subMonthNoOverflow()->startOfMonth();

            return new self('custom', $start, $start->endOfMonth());
        }

        if (in_array($this->preset, ['this_month', 'this_year'], true)) {
            $unit = $this->preset === 'this_month' ? 'subMonthNoOverflow' : 'subYear';
            $start = $this->start->{$unit}();

            return new self('custom', $start, $this->end->{$unit}());
        }

        $days = (int) $this->start->diffInDays($this->end->startOfDay()) + 1;

        return new self('custom', $this->start->subDays($days), $this->end->subDays($days));
    }

    public function startDate(): string
    {
        return $this->start->toDateString();
    }

    public function endDate(): string
    {
        return $this->end->toDateString();
    }

    /**
     * Inclusive boundaries for DATE columns (order date, receipt date, payment date...). The end carries 23:59:59 so
     * the last day is included whether the driver stores a pure date (MySQL) or a date-time string (SQLite).
     *
     * @return array{0: string, 1: string}
     */
    public function dateRange(): array
    {
        return [$this->startDate(), $this->endDate().' 23:59:59'];
    }

    /**
     * Boundaries for timestamp columns, converted from the business timezone to the storage timezone.
     *
     * @return array{0: string, 1: string}
     */
    public function timestampRange(): array
    {
        $storage = config('app.timezone');

        return [
            $this->start->setTimezone($storage)->toDateTimeString(),
            $this->end->setTimezone($storage)->toDateTimeString(),
        ];
    }

    public function label(): string
    {
        $range = $this->startDate() === $this->endDate()
            ? $this->startDate()
            : $this->startDate().' ← '.$this->endDate();

        return ($this->preset === 'custom' ? '' : self::PRESETS[$this->preset].' — ').$range;
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return $this->preset === 'custom'
            ? ['period' => 'custom', 'from' => $this->startDate(), 'to' => $this->endDate()]
            : ['period' => $this->preset];
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
