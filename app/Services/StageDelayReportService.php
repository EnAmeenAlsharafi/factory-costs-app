<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ProductionOrderOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which department holds orders up: stages late now, and how each department kept to its target
 * time for the stages it finished in the month.
 */
class StageDelayReportService
{
    public function __construct(protected StageDeadlineService $deadlines) {}

    /**
     * @return array{departments: Collection<int, array<string, mixed>>, late: Collection<int, array{operation: ProductionOrderOperation, deadline: array<string, mixed>}>, totals: array<string, int|float|null>}
     */
    public function report(Carbon $month, User $viewer, ?int $departmentId = null): array
    {
        $departmentId ??= $viewer->isDepartmentRestricted() ? $viewer->productionDepartmentId() : null;
        $inDepartment = fn (Builder $query) => $query->when($departmentId, fn (Builder $q) => $q->whereHas('workCenter', fn (Builder $w) => $w->where('department_id', $departmentId)));

        $open = ProductionOrderOperation::query()
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['COMPLETED', 'SKIPPED'])
            ->whereHas('productionOrder', fn (Builder $query) => $query->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'ON_HOLD']))
            ->tap($inDepartment)
            ->with('workCenter.department', 'blocks', 'assignedUser', 'productionOrder.customerOrder.customer', 'productionOrder.productModel')
            ->get()
            ->map(fn (ProductionOrderOperation $operation) => ['operation' => $operation, 'deadline' => $this->deadlines->status($operation)]);

        $finished = ProductionOrderOperation::query()
            ->whereNotNull('due_at')
            ->where('status', 'COMPLETED')
            ->whereBetween('completed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->tap($inDepartment)
            ->with('workCenter.department', 'blocks')
            ->get()
            ->map(fn (ProductionOrderOperation $operation) => [
                'operation' => $operation,
                'deadline' => $this->deadlines->status($operation),
                'actual_days' => $this->deadlines->workingDaysBetween($operation->ready_at, $operation->completed_at),
            ]);

        $departments = Department::where('is_production_department', true)
            ->when($departmentId, fn (Builder $query) => $query->whereKey($departmentId))
            ->orderBy('id')
            ->get()
            ->map(function (Department $department) use ($open, $finished) {
                $ownOpen = $open->filter(fn ($row) => $row['operation']->workCenter?->department_id === $department->id);
                $ownFinished = $finished->filter(fn ($row) => $row['operation']->workCenter?->department_id === $department->id);
                $lateFinished = $ownFinished->where('deadline.state', 'done_late');

                return [
                    'department' => $department,
                    'open' => $ownOpen->count(),
                    'overdue' => $ownOpen->where('deadline.state', 'overdue')->count(),
                    'warning' => $ownOpen->where('deadline.state', 'warning')->count(),
                    'finished' => $ownFinished->count(),
                    'on_time_rate' => $ownFinished->isNotEmpty() ? (int) round($ownFinished->where('deadline.state', 'done_on_time')->count() / $ownFinished->count() * 100) : null,
                    'average_days' => $ownFinished->isNotEmpty() ? round($ownFinished->avg('actual_days'), 1) : null,
                    'average_delay' => $lateFinished->isNotEmpty() ? round($lateFinished->avg('deadline.days'), 1) : null,
                ];
            });

        $late = $open->where('deadline.state', 'overdue')->sortByDesc('deadline.days')->values();

        return [
            'departments' => $departments,
            'late' => $late,
            'totals' => [
                'overdue' => $late->count(),
                'warning' => $open->where('deadline.state', 'warning')->count(),
                'open' => $open->count(),
                'on_time_rate' => $finished->isNotEmpty() ? (int) round($finished->where('deadline.state', 'done_on_time')->count() / $finished->count() * 100) : null,
                'unconfigured' => Department::where('is_production_department', true)->whereNull('target_days')->count(),
            ],
        ];
    }
}
