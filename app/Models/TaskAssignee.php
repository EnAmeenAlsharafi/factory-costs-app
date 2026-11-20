<?php

namespace App\Models;

use Database\Factories\TaskAssigneeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One employee's copy of a task: their own status and due date, and their personal flags.
 */
class TaskAssignee extends Model
{
    /** @use HasFactory<TaskAssigneeFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_DONE = 'DONE';

    /** The author accepted the employee's request to be excused; the task left their lists. */
    public const STATUS_DECLINED = 'DECLINED';

    /**
     * Statuses the employee may set themselves.
     *
     * @var array<string, string>
     */
    public const WORK_STATUSES = [
        self::STATUS_OPEN => 'جديدة',
        self::STATUS_IN_PROGRESS => 'قيد التنفيذ',
        self::STATUS_DONE => 'منجزة',
    ];

    protected $fillable = [
        'task_id',
        'user_id',
        'task_list_id',
        'status',
        'due_date',
        'is_important',
        'my_day_on',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'my_day_on' => 'date',
            'is_important' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(TaskRequest::class);
    }

    public function pendingRequest(): HasOne
    {
        return $this->hasOne(TaskRequest::class)->where('status', TaskRequest::STATUS_PENDING)->latestOfMany();
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && ! $this->isDeclined() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function isInMyDay(): bool
    {
        return $this->my_day_on !== null && $this->my_day_on->isToday();
    }

    public function statusLabel(): string
    {
        return self::WORK_STATUSES[$this->status] ?? 'اعتُذر عنها';
    }

    /**
     * Still on the employee's plate: not finished, not excused, and the task not cancelled.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])
            ->whereHas('task', fn (Builder $task) => $task->whereNull('cancelled_at'));
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->id : $user);
    }
}
