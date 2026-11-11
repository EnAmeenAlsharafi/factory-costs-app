<?php

namespace App\Models;

use App\Notifications\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One operational message, delivered to one or more recipients. The title and body are snapshots:
 * renaming a model later never rewrites what the factory was told at the time.
 */
class Notification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'source',
        'severity',
        'title_ar',
        'body_ar',
        'url',
        'subject_type',
        'subject_id',
        'department_id',
        'triggered_by_user_id',
        'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'created_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * Raised by the scheduled scan rather than by someone's action.
     */
    public function isAutomatic(): bool
    {
        return $this->source === 'SYSTEM';
    }

    public function severityTone(): string
    {
        return match ($this->severity) {
            'danger' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => 'secondary',
        };
    }

    public function scopeOfType(Builder $query, NotificationType|string $type): Builder
    {
        return $query->where('type', $type instanceof NotificationType ? $type->value : $type);
    }
}
