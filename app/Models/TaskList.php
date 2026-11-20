<?php

namespace App\Models;

use Database\Factories\TaskListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A list an employee makes for their own tasks («متابعة موردين»). Private to its owner.
 */
class TaskList extends Model
{
    /** @use HasFactory<TaskListFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }
}
