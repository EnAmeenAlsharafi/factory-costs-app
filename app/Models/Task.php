<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task as its author wrote it. Who works on it, and how far each one got, lives in TaskAssignee.
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'details',
        'due_date',
        'created_by_user_id',
        'is_assigned',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_assigned' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isCreatedBy(User $user): bool
    {
        return $this->created_by_user_id === $user->id;
    }
}
