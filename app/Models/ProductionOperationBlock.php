<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Structured block lifecycle for a production operation (spec §20–22, §76).
 *
 * History is preserved: resolving a block updates the same row with
 * resolution data and clears is_active; it is never deleted.
 */
class ProductionOperationBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_operation_id',
        'production_order_id',
        'reason_code',
        'note',
        'previous_status',
        'blocked_by_user_id',
        'blocked_at',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_note',
        'production_material_requirement_id',
        'production_material_request_id',
        'is_active',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialRequirement::class, 'production_material_requirement_id');
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialRequest::class, 'production_material_request_id');
    }

    public function getReasonArabicAttribute(): string
    {
        return match ($this->reason_code) {
            'MATERIAL_SHORTAGE' => 'نقص مواد',
            'SPEC_ISSUE' => 'مشكلة في المواصفات',
            'QUALITY_ISSUE' => 'مشكلة جودة',
            'AWAITING_MANAGER' => 'انتظار قرار مدير الإنتاج',
            'EQUIPMENT_FAILURE' => 'عطل معدات',
            'OTHER' => 'سبب آخر',
            default => $this->reason_code,
        };
    }

    /**
     * Block duration in minutes (spec §76), or null while still active.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        return (int) $this->blocked_at->diffInMinutes($this->resolved_at);
    }
}
