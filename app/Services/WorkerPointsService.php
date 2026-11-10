<?php

namespace App\Services;

use App\Models\ProductionOperationProgress;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionPointCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Worker points: every piece finished in a department that awards points earns the worker the
 * points of the bed's category (the order line's own category for custom and showroom designs,
 * otherwise the model's). The rate is snapshotted on the progress record, so editing a category
 * later never rewrites a month that was already rewarded.
 */
class WorkerPointsService
{
    /**
     * The category and rate that apply to work done on this operation, or null when its
     * department does not award points. A category of null means the bed is not classified yet.
     *
     * @return array{category: ?ProductionPointCategory}|null
     */
    public function rateFor(ProductionOrderOperation $operation): ?array
    {
        $operation->loadMissing('workCenter.department', 'productionOrder.customerOrderLine.pointCategory', 'productionOrder.productModel.pointCategory');

        if (! $operation->workCenter?->department?->awards_points) {
            return null;
        }
        $productionOrder = $operation->productionOrder;

        return ['category' => $productionOrder?->customerOrderLine?->pointCategory ?? $productionOrder?->productModel?->pointCategory];
    }

    /**
     * Who gets credited and how many points, for a progress record about to be written.
     *
     * @return array{worker_user_id: ?int, point_category_id: ?int, points_per_unit: ?float, points: ?float}
     */
    public function creditFor(ProductionOrderOperation $operation, int $quantity, ?int $workerId): array
    {
        $rate = $this->rateFor($operation);
        $category = $rate['category'] ?? null;

        return [
            'worker_user_id' => $workerId,
            'point_category_id' => $category?->id,
            'points_per_unit' => $category ? (float) $category->points : null,
            'points' => $category ? round($quantity * (float) $category->points, 2) : null,
        ];
    }

    /**
     * Credit work recorded before its bed was classified: progress in a points department during
     * the month with no category yet takes the category the bed has now.
     */
    public function creditUnclassified(Carbon $month): int
    {
        $credited = 0;
        ProductionOperationProgress::query()
            ->whereNull('point_category_id')
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereHas('operation.workCenter.department', fn ($query) => $query->where('awards_points', true))
            ->with('operation')
            ->chunkById(200, function ($records) use (&$credited) {
                foreach ($records as $record) {
                    $category = $this->rateFor($record->operation)['category'] ?? null;
                    if (! $category) {
                        continue;
                    }
                    $record->update([
                        'point_category_id' => $category->id,
                        'points_per_unit' => $category->points,
                        'points' => round($record->quantity * (float) $category->points, 2),
                    ]);
                    $credited++;
                }
            });

        return $credited;
    }

    /**
     * Points earned per worker in the month: worker id => points.
     *
     * @return array<int, float>
     */
    public function monthlyPoints(Carbon $month): array
    {
        return DB::table('production_operation_progress')
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereNotNull('worker_user_id')
            ->groupBy('worker_user_id')
            ->pluck(DB::raw('COALESCE(SUM(points), 0)'), 'worker_user_id')
            ->map(fn ($points) => round((float) $points, 2))
            ->all();
    }
}
