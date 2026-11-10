<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkerPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee performance and monthly points across the production departments.
 */
class WorkerPerformanceReportController extends Controller
{
    public function __construct(protected WorkerPerformanceService $performance) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('reports.production'), 403);
        [$month, $departmentId] = $this->filters($request);
        $report = $this->performance->monthly($month, $request->user(), $departmentId);
        $departments = $this->performance->departmentsFor($request->user());

        return view('management_reports.workers.index', compact('month', 'departmentId', 'report', 'departments'));
    }

    public function show(Request $request, User $worker): View
    {
        abort_unless($request->user()->can('reports.production'), 403);
        abort_if($request->user()->isDepartmentRestricted() && $worker->department_id !== $request->user()->productionDepartmentId(), 403);
        [$month] = $this->filters($request);
        $report = $this->performance->workerMonth($worker->load('department'), $month, $request->user());

        return view('management_reports.workers.show', compact('worker', 'month', 'report'));
    }

    /**
     * The month's points sheet for payroll, one row per employee (opens in Excel with Arabic intact).
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.production'), 403);
        [$month, $departmentId] = $this->filters($request);
        $rows = $this->performance->monthly($month, $request->user(), $departmentId)['rows'];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['الموظف', 'القسم', 'القطع المنجزة', 'النقاط', 'قطع بلا تصنيف', 'أيام العمل', 'متوسط القطع لليوم', 'مراحل مكتملة']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['worker']->name, $row['department'], $row['pieces'], $row['points'], $row['unclassified_pieces'], $row['active_days'], $row['per_day'], $row['operations_finished']]);
            }
            fclose($out);
        }, 'worker-points-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: ?int}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);
        $month = isset($validated['month']) ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth() : now()->startOfMonth();

        return [$month, isset($validated['department_id']) ? (int) $validated['department_id'] : null];
    }
}
