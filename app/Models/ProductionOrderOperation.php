<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'started_at',
        'completed_at',
        'assigned_user_id',
        'notes',
    ];

    protected $casts = [
        'sequence_number' => 'integer',
        'required_quantity' => 'integer',
        'started_quantity' => 'integer',
        'completed_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProductionOperationProgress::class)->orderBy('occurred_at', 'desc');
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

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'PENDING' => 'bg-secondary',
            'READY' => 'bg-info text-dark',
            'IN_PROGRESS' => 'bg-warning text-dark',
            'PARTIALLY_COMPLETED' => 'bg-primary',
            'COMPLETED' => 'bg-success',
            'BLOCKED' => 'bg-danger',
            'SKIPPED' => 'bg-dark text-white',
            default => 'bg-secondary',
        };
    }

    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'PENDING' => 'في الانتظار',
            'READY' => 'جاهز للبدء',
            'IN_PROGRESS' => 'قيد التنفيذ',
            'PARTIALLY_COMPLETED' => 'منجز جزئياً',
            'COMPLETED' => 'مكتمل',
            'BLOCKED' => 'محظور / متوقف',
            'SKIPPED' => 'متخطي',
            default => $this->status,
        };
    }
}
