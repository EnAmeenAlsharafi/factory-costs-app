<?php

namespace App\Domain\Reports;

use Closure;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Collection;

/**
 * A database-aggregated report source: the query (never loaded wholesale into PHP), a row mapper and an optional
 * enrichment step that runs once per page/chunk (e.g. to reuse an authoritative model accessor for 25 rows at a time).
 */
final class ReportDataset
{
    /**
     * @param  Closure(object): array<string, mixed>  $mapper
     * @param  (Closure(Collection<int, array<string, mixed>>): Collection<int, array<string, mixed>>)|null  $enricher
     * @param  array<string, mixed>|null  $totals
     */
    public function __construct(
        public readonly BuilderContract $query,
        public readonly Closure $mapper,
        public readonly ?Closure $enricher = null,
        public readonly ?array $totals = null,
    ) {}

    /**
     * @param  iterable<object>  $records
     * @return Collection<int, array<string, mixed>>
     */
    public function mapRows(iterable $records): Collection
    {
        $rows = collect($records)->map(fn ($record) => ($this->mapper)($record))->values();

        return $this->enricher ? ($this->enricher)($rows)->values() : $rows;
    }
}
