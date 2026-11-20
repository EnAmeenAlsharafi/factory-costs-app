<?php

namespace App\Models;

use Database\Factories\TaskRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an assignee asks the task's author: to be excused from it, or for a later due date.
 */
class TaskRequest extends Model
{
    /** @use HasFactory<TaskRequestFactory> */
    use HasFactory;

    public const TYPE_DECLINE = 'DECLINE';

    public const TYPE_EXTEND = 'EXTEND';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'task_assignee_id',
        'type',
        'reason',
        'requested_due_date',
        'status',
        'decided_by_user_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_due_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaskAssignee::class, 'task_assignee_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_DECLINE ? 'طلب اعتذار' : 'طلب تمديد';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'مقبول',
            self::STATUS_REJECTED => 'مرفوض',
            default => 'بانتظار الرد',
        };
    }
}
