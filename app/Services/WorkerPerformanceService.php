<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ProductionOperationProgress;
use App\Models\ProductionOrderOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Employee performance for a month, from the production progress ledger: pieces finished, points
 * earned (for the monthly reward), working days, and what each employee holds open right now.
 * Work is credited to the employee who did it (the operation's assignee), not to whoever typed it in.
 */
class WorkerPerformanceService
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, float|int>, unclassified_pieces: float}
     */
    public function monthly(Carbon $month, User $viewer, ?int $departmentId = null): array
    {
        $records = $this->progressQuery($month, $viewer, $departmentId)
            ->with('operation.workCenter.department')
            ->get();

        $departmentIds = $departmentId ? [$departmentId] : ($viewer->isDepartmentRestricted() ? [$viewer->productionDepartmentId()] : null);
        $staff = User::query()->where('is_active', true)
            ->whereNotNull('department_id')
            ->whereHas('department', fn (Builder $query) => $query->where('is_production_department', true))
            ->when($departmentIds, fn (Builder $query) => $query->whereIn('department_id', $departmentIds))
            ->with('department')
            ->get()
            ->keyBy('id');
        $workers = $staff->union(User::with('department')->whereIn('id', $records->pluck('worker_user_id')->unique()->diff($staff->keys()))->get()->keyBy('id'));

        $openWork = ProductionOrderOperation::query()
            ->whereIn('assigned_user_id', $workers->keys())
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'PARTIALLY_COMPLETED', 'BLOCKED'])
            ->get(['id', 'assigned_user_id', 'status'])
            ->groupBy('assigned_user_id');

        $rows = $workers->map(function (User $worker) use ($records, $openWork) {
            $own = $records->where('worker_user_id', $worker->id);
            $pieces = (float) $own->sum('quantity');
            $activeDays = $own->map(fn ($record) => $record->occurred_at->toDateString())->unique()->count();
            $open = $openWork->get($worker->id, collect());

            return [
                'worker' => $worker,
                'department' => $worker->department?->name_ar ?? $own->first()?->operation?->workCenter?->department?->name_ar ?? '—',
                'pieces' => $pieces,
                'points' => round((float) $own->sum('points'), 2),
                'unclassified_pieces' => (float) $own->whereNull('point_category_id')->filter(fn ($record) => $record->operation?->workCenter?->department?->awards_points)->sum('quantity'),
                'operations_finished' => $own->filter(fn ($record) => $record->quantity > 0 && $record->operation && $record->new_completed_quantity >= $record->operation->required_quantity)->count(),
                'active_days' => $activeDays,
                'per_day' => $activeDays > 0 ? round($pieces / $activeDays, 1) : 0.0,
                'open_tasks' => $open->where('status', '!=', 'BLOCKED')->count(),
                'blocked_tasks' => $open->where('status', 'BLOCKED')->count(),
            ];
        })
            ->sortBy([['points', 'desc'], ['pieces', 'desc']])
            ->values();

        $active = $rows->where('pieces', '>', 0);

        return [
            'rows' => $rows,
            'totals' => [
                'pieces' => $rows->sum('pieces'),
                'points' => round($rows->sum('points'), 2),
                'active_workers' => $active->count(),
                'workers' => $rows->count(),
                'average_points' => $active->isNotEmpty() ? round($active->avg('points'), 2) : 0,
            ],
            'unclassified_pieces' => $rows->sum('unclassified_pieces'),
        ];
    }

    /**
     * One employee's month: daily output, points by bed category, and every piece of work recorded.
     *
     * @return array{records: Collection<int, ProductionOperationProgress>, daily: Collection<string, array{pieces: float, points: float}>, categories: Collection<int, array{name: string, rate: ?float, pieces: float, points: float}>, totals: array<string, float|int>}
     */
    public function workerMonth(User $worker, Carbon $month, User $viewer): array
    {
        $records = $this->progressQuery($month, $viewer)
            ->where('worker_user_id', $worker->id)
            ->with(['operation.workCenter.department', 'operation.productionOrder.customerOrder', 'pointCategory', 'user'])
            ->orderBy('occurred_at')
            ->get();

        $daily = collect();
        for ($day = $month->copy()->startOfMonth(); $day->lte($month->copy()->endOfMonth()); $day->addDay()) {
            $own = $records->filter(fn ($record) => $record->occurred_at->isSameDay($day));
            $daily->put($day->toDateString(), ['pieces' => (float) $own->sum('quantity'), 'points' => round((float) $own->sum('points'), 2)]);
        }

        $categories = $records->groupBy(fn ($record) => $record->point_category_id ?? 0)
            ->map(fn (Collection $group, $categoryId) => [
                'name' => $categoryId ? $group->first()->pointCategory?->name_ar ?? 'تصنيف محذوف' : 'بلا تصنيف',
                'rate' => $categoryId ? (float) $group->first()->points_per_unit : null,
                'pieces' => (float) $group->sum('quantity'),
                'points' => round((float) $group->sum('points'), 2),
            ])
            ->sortByDesc('points')
            ->values();

        $activeDays = $daily->filter(fn ($day) => $day['pieces'] != 0)->count();

        return [
            'records' => $records->sortByDesc('occurred_at')->values(),
            'daily' => $daily,
            'categories' => $categories,
            'totals' => [
                'pieces' => (float) $records->sum('quantity'),
                'points' => round((float) $records->sum('points'), 2),
                'active_days' => $activeDays,
                'per_day' => $activeDays > 0 ? round($records->sum('quantity') / $activeDays, 1) : 0,
            ],
        ];
    }

    /**
     * Progress recorded in the month, limited to the viewer's department when they are restricted to one.
     *
     * @return Builder<ProductionOperationProgress>
     */
    private function progressQuery(Carbon $month, User $viewer, ?int $departmentId = null): Builder
    {
        $departmentId ??= $viewer->isDepartmentRestricted() ? $viewer->productionDepartmentId() : null;

        return ProductionOperationProgress::query()
            ->whereNotNull('worker_user_id')
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($departmentId, fn (Builder $query) => $query->whereHas('operation.workCenter', fn (Builder $workCenter) => $workCenter->where('department_id', $departmentId)));
    }

    /**
     * Departments the viewer may filter by.
     *
     * @return Collection<int, Department>
     */
    public function departmentsFor(User $viewer): Collection
    {
        return Department::where('is_production_department', true)
            ->when($viewer->isDepartmentRestricted(), fn (Builder $query) => $query->whereKey($viewer->productionDepartmentId()))
            ->orderBy('id')
            ->get();
    }
}
