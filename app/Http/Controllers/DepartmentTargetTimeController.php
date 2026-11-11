<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProductionOrderOperation;
use App\Services\StageDeadlineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Department target times: how many working days an order may spend in each production department.
 */
class DepartmentTargetTimeController extends Controller
{
    public function index(Request $request, StageDeadlineService $deadlines): View
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);

        $departments = Department::where('is_production_department', true)->orderBy('id')->get();
        $openStages = ProductionOrderOperation::query()
            ->whereNotNull('ready_at')
            ->whereNotIn('status', ['COMPLETED', 'SKIPPED'])
            ->whereHas('productionOrder', fn ($query) => $query->whereNotIn('status', ['CANCELLED', 'COMPLETED']))
            ->with('workCenter.department', 'blocks')
            ->get();
        $stats = $departments->mapWithKeys(function (Department $department) use ($openStages, $deadlines) {
            $own = $openStages->filter(fn ($stage) => $stage->workCenter?->department_id === $department->id);

            return [$department->id => [
                'open' => $own->count(),
                'overdue' => $own->filter(fn ($stage) => $deadlines->status($stage)['state'] === 'overdue')->count(),
                'without_deadline' => $own->whereNull('due_at')->count(),
            ]];
        });

        return view('production.department_times.index', compact('departments', 'stats'));
    }

    public function update(Request $request, StageDeadlineService $deadlines): RedirectResponse
    {
        abort_unless($request->user()->can('production.manage_routing'), 403);
        $validated = $request->validate([
            'departments' => ['required', 'array'],
            'departments.*.target_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'departments.*.warning_percent' => ['required', 'integer', 'min:10', 'max:95'],
            'apply_to_open' => ['nullable', 'boolean'],
        ], [], ['departments.*.target_days' => 'مدة القسم', 'departments.*.warning_percent' => 'نسبة التنبيه']);

        $applied = 0;
        foreach (Department::where('is_production_department', true)->whereIn('id', array_keys($validated['departments']))->get() as $department) {
            $values = $validated['departments'][$department->id];
            $department->update(['target_days' => $values['target_days'] ?? null, 'warning_percent' => $values['warning_percent']]);
            if ($request->boolean('apply_to_open')) {
                $applied += $deadlines->applyToOpenStages($department);
            }
        }

        return back()->with('success', 'تم حفظ مدد الأقسام. المدد الجديدة تُطبّق على الطلبات التي تصل للأقسام من الآن'.($applied > 0 ? "، وأُعطيت {$applied} مرحلة موجودة حالياً موعداً." : '.'));
    }
}
