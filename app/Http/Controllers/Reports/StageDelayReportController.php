<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\StageDelayReportService;
use App\Services\WorkerPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Delays by department: stages late now and each department's on-time record for the month.
 */
class StageDelayReportController extends Controller
{
    public function __invoke(Request $request, StageDelayReportService $delays, WorkerPerformanceService $performance): View
    {
        abort_unless($request->user()->can('reports.production'), 403);
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);
        $month = isset($validated['month']) ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth() : now()->startOfMonth();
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        $report = $delays->report($month, $request->user(), $departmentId);
        $departments = $performance->departmentsFor($request->user());

        return view('management_reports.production.delays', compact('report', 'month', 'departmentId', 'departments'));
    }
}
