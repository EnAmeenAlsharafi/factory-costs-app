<?php

namespace App\Domain\Reports;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A prepared report table: permission-filtered columns, rows of raw values, optional totals and pagination.
 * Controllers/services build it; Blade only renders it (no queries in views).
 */
final class ReportTable
{
    /**
     * @param  list<ReportColumn>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    public function __construct(
        public array $columns,
        public iterable $rows,
        public ?array $totals = null,
        public ?LengthAwarePaginator $paginator = null,
        public string $emptyMessage = 'لا توجد بيانات ضمن الفترة والفلاتر المحددة.',
    ) {}

    /**
     * @param  list<ReportColumn>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    public static function for(User $user, array $columns, iterable $rows, ?array $totals = null, ?LengthAwarePaginator $paginator = null, ?string $emptyMessage = null): self
    {
        $visible = array_values(array_filter(
            $columns,
            fn (ReportColumn $column) => collect($column->abilities)->every(fn (string $ability) => $user->can($ability))
        ));

        return new self($visible, $rows, $totals, $paginator, $emptyMessage ?? 'لا توجد بيانات ضمن الفترة والفلاتر المحددة.');
    }

    public function hasColumn(string $key): bool
    {
        return collect($this->columns)->contains(fn (ReportColumn $column) => $column->key === $key);
    }
}
