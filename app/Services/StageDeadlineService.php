<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ProductionOrderOperation;
use Illuminate\Support\Carbon;

/**
 * Stage deadlines: a stage must leave its department within the department's target working days,
 * counted from the moment it reached the department (became ready). Friday is not a working day.
 * Time spent stopped on a recorded blocker (missing material, ...) does not count against the
 * department: it pushes the deadline back by the blocked time.
 */
class StageDeadlineService
{
    /**
     * Weekly days off (Carbon day-of-week numbers).
     */
    public const DAYS_OFF = [Carbon::FRIDAY];

    /**
     * $start plus $days working days, skipping days off.
     */
    public function addWorkingDays(Carbon $start, int $days): Carbon
    {
        $due = $start->copy();
        $added = 0;
        while ($added < $days) {
            $due->addDay();
            if (! in_array($due->dayOfWeek, self::DAYS_OFF, true)) {
                $added++;
            }
        }

        return $due;
    }

    /**
     * Whole working days from $from to $to, at least 1 when $to is later (0 otherwise). With $roundUp a
     * part day counts as a full one (time left); without it only full days count (how late, how long).
     */
    public function workingDaysBetween(Carbon $from, Carbon $to, bool $roundUp = false): int
    {
        if ($to->lte($from)) {
            return 0;
        }
        $days = 0;
        $cursor = $from->copy();
        while ($cursor->copy()->addDay()->lte($to)) {
            $cursor->addDay();
            if (! in_array($cursor->dayOfWeek, self::DAYS_OFF, true)) {
                $days++;
            }
        }
        if ($roundUp && $cursor->lt($to)) {
            $days++;
        }

        return max(1, $days);
    }

    /**
     * Snapshot the department's target onto a stage the moment it reaches the department.
     */
    public function stamp(ProductionOrderOperation $operation): void
    {
        if (! $operation->ready_at || $operation->target_days !== null) {
            return;
        }
        $targetDays = $operation->workCenter?->department?->target_days;
        if (! $targetDays) {
            return;
        }
        $operation->target_days = $targetDays;
        $operation->due_at = $this->addWorkingDays($operation->ready_at, $targetDays);
    }

    /**
     * Give a deadline to stages already in a department that have none yet (sent there before the
     * department had a target), counted from when each one arrived.
     */
    public function applyToOpenStages(Department $department): int
    {
        if (! $department->target_days) {
            return 0;
        }

        $stages = ProductionOrderOperation::query()
            ->whereNotNull('ready_at')
            ->whereNull('target_days')
            ->whereNotIn('status', ['COMPLETED', 'SKIPPED'])
            ->whereHas('workCenter', fn ($query) => $query->where('department_id', $department->id))
            ->get();
        $stages->each(fn (ProductionOrderOperation $stage) => $stage->save());

        return $stages->count();
    }

    /**
     * Seconds the stage spent stopped on blockers (an open blocker counts up to now).
     */
    public function blockedSeconds(ProductionOrderOperation $operation, ?Carbon $until = null): int
    {
        $until ??= now();

        return (int) $operation->blocks
            ->filter(fn ($block) => $block->blocked_at)
            ->sum(fn ($block) => max(0, $block->blocked_at->diffInSeconds(($block->resolved_at ?? $until)->min($until), false)));
    }

    /**
     * Where a stage stands against its deadline.
     *
     * @return array{state: string, label: string, tone: string, due_at: ?Carbon, days: int, blocked: bool}
     *                                                                                                      state: none | on_track | warning | overdue | done_on_time | done_late
     */
    public function status(ProductionOrderOperation $operation, ?Carbon $now = null): array
    {
        $now ??= now();
        $none = ['state' => 'none', 'label' => '', 'tone' => 'secondary', 'due_at' => null, 'days' => 0, 'blocked' => false];
        if (! $operation->due_at || ! $operation->ready_at || $operation->status === 'SKIPPED') {
            return $none;
        }

        $finishedAt = $operation->status === 'COMPLETED' ? ($operation->completed_at ?? $now) : null;
        $blockedSeconds = $this->blockedSeconds($operation, $finishedAt ?? $now);
        $due = $operation->due_at->copy()->addSeconds($blockedSeconds);
        $blocked = $blockedSeconds > 0;

        if ($finishedAt) {
            $late = $finishedAt->gt($due) ? $this->workingDaysBetween($due, $finishedAt) : 0;

            return $late > 0
                ? ['state' => 'done_late', 'label' => 'أُنجز متأخراً '.$this->days($late), 'tone' => 'warning', 'due_at' => $due, 'days' => $late, 'blocked' => $blocked]
                : ['state' => 'done_on_time', 'label' => 'أُنجز في الوقت', 'tone' => 'success', 'due_at' => $due, 'days' => 0, 'blocked' => $blocked];
        }

        if ($now->gt($due)) {
            $late = $this->workingDaysBetween($due, $now);

            return ['state' => 'overdue', 'label' => 'متأخر '.$this->days($late), 'tone' => 'danger', 'due_at' => $due, 'days' => $late, 'blocked' => $blocked];
        }

        $left = $this->workingDaysBetween($now, $due, roundUp: true);
        $total = max(1, $operation->ready_at->diffInSeconds($due));
        $elapsedPercent = $operation->ready_at->diffInSeconds($now) / $total * 100;
        $warningPercent = $operation->workCenter?->department?->warning_percent ?? 75;
        $label = $left <= 1 ? 'الموعد خلال يوم' : 'متبقٍ '.$this->days($left);

        return $elapsedPercent >= $warningPercent
            ? ['state' => 'warning', 'label' => $label, 'tone' => 'warning', 'due_at' => $due, 'days' => $left, 'blocked' => $blocked]
            : ['state' => 'on_track', 'label' => $label, 'tone' => 'success', 'due_at' => $due, 'days' => $left, 'blocked' => $blocked];
    }

    /**
     * «يوم» / «يومين» / «3 أيام» / «12 يوماً».
     */
    public function days(int $days): string
    {
        return match (true) {
            $days === 1 => 'يوم',
            $days === 2 => 'يومين',
            $days <= 10 => "{$days} أيام",
            default => "{$days} يوماً",
        };
    }
}
