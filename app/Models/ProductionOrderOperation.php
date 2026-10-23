<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductionOrderOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'routing_operation_id',
        'work_center_id',
        'operation_code',
        'operation_name_snapshot',
        'branch_key',
        'sequence_number',
        'required_quantity',
        'started_quantity',
        'completed_quantity',
        'rejected_quantity',
        'status',
        'ready_at',
        'started_at',
        'completed_at',
        'assigned_user_id',
        'assigned_at',
        'assigned_by_user_id',
        'notes',
    ];

    protected $casts = [
        'sequence_number' => 'integer',
        'required_quantity' => 'integer',
        'started_quantity' => 'integer',
        'completed_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'ready_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'assigned_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionRoutingOperation::class, 'routing_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProductionOperationProgress::class)->orderBy('occurred_at', 'desc');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionOperationEvent::class)->orderBy('occurred_at', 'desc');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ProductionOperationBlock::class)->orderBy('blocked_at', 'desc');
    }

    public function activeBlock(): HasOne
    {
        return $this->hasOne(ProductionOperationBlock::class)->where('is_active', true);
    }

    public function qualityIncidents(): HasMany
    {
        return $this->hasMany(QualityIncident::class, 'production_order_operation_id');
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(ProductionWasteRecord::class, 'production_order_operation_id');
    }

    public function reworkActions(): HasMany
    {
        return $this->hasMany(ProductionReworkAction::class, 'source_operation_id');
    }

    /**
     * Rework actions that send work back to this operation (i.e. this operation is being re-done).
     */
    public function targetReworkActions(): HasMany
    {
        return $this->hasMany(ProductionReworkAction::class, 'target_operation_id');
    }

    /**
     * Remaining quantity still to complete for this operation.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->required_quantity - (int) $this->completed_quantity);
    }

    /**
     * Whether this operation is effectively assigned to an employee.
     */
    public function getIsAssignedAttribute(): bool
    {
        return $this->assigned_user_id !== null;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'PENDING' => 'bg-secondary',
            'READY' => 'bg-info text-dark',
            'ASSIGNED' => 'bg-primary',
            'IN_PROGRESS' => 'bg-warning text-dark',
            'PARTIALLY_COMPLETED' => 'bg-warning text-dark',
            'COMPLETED' => 'bg-success',
            'BLOCKED' => 'bg-danger',
            'SKIPPED' => 'bg-dark text-white',
            default => 'bg-secondary',
        };
    }

    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'PENDING' => 'بانتظار المرحلة السابقة',
            'READY' => 'جاهز للتنفيذ',
            'ASSIGNED' => 'تم الإسناد',
            'IN_PROGRESS' => 'قيد التنفيذ',
            'PARTIALLY_COMPLETED' => 'منجز جزئياً',
            'COMPLETED' => 'مكتمل',
            'BLOCKED' => 'متوقف',
            'SKIPPED' => 'ملغي',
            default => $this->status,
        };
    }
}
