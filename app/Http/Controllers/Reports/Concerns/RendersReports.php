<?php

namespace App\Http\Controllers\Reports\Concerns;

use App\Domain\Reports\ReportColumn;
use App\Domain\Reports\ReportDataset;
use App\Domain\Reports\ReportPeriod;
use App\Domain\Reports\ReportTable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait RendersReports
{
    protected const PAGE_SIZE = 25;

    protected function wantsExport(Request $request): bool
    {
        return $request->query('export') === 'csv';
    }

    /**
     * Paginated screen table from a dataset. Columns are filtered by the viewer's permissions.
     *
     * @param  list<ReportColumn>  $columns
     */
    protected function paginatedTable(Request $request, ReportDataset $dataset, array $columns, ?string $emptyMessage = null, string $pageName = 'page'): ReportTable
    {
        $paginator = $dataset->query->paginate(self::PAGE_SIZE, ['*'], $pageName)->withQueryString();
        $rows = $dataset->mapRows($paginator->items());

        return ReportTable::for($request->user(), $columns, $rows, $dataset->totals, $paginator, $emptyMessage);
    }

    /**
     * Streams the whole filtered dataset as CSV (UTF-8 with BOM for Arabic in spreadsheets), using exactly the
     * columns the user can see on screen. Requires reports.export in addition to the report's own permission.
     *
     * @param  list<ReportColumn>  $columns
     * @param  array<string, string>  $appliedFilters
     */
    protected function exportCsv(Request $request, ReportDataset $dataset, array $columns, string $title, string $filename, ?ReportPeriod $period = null, array $appliedFilters = []): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403, 'غير مصرح لك بتصدير التقارير.');

        $table = ReportTable::for($request->user(), $columns, []);

        return response()->streamDownload(function () use ($dataset, $table, $title, $period, $appliedFilters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$title]);
            if ($period) {
                fputcsv($out, ['الفترة', $period->label()]);
            }
            foreach ($appliedFilters as $label => $value) {
                fputcsv($out, [$label, $value]);
            }
            fputcsv($out, ['تاريخ التوليد', now()->format('Y-m-d H:i')]);
            fputcsv($out, []);
            fputcsv($out, array_map(fn (ReportColumn $column) => $column->label, $table->columns));

            foreach ($dataset->query->cursor()->chunk(500) as $chunk) {
                foreach ($dataset->mapRows($chunk) as $row) {
                    fputcsv($out, array_map(fn (ReportColumn $column) => $column->export($row[$column->key] ?? null), $table->columns));
                }
            }

            fclose($out);
        }, $filename.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
