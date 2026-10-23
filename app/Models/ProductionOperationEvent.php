<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only execution event history for a production operation.
 *
 * This is the canonical source consumed by the unified order timeline
 * (spec §27, §62, §121). Rows are never updated or deleted in business logic.
 */
class ProductionOperationEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'production_order_id',
        'production_order_operation_id',
        'event_type',
        'from_status',
        'to_status',
        'quantity',
        'user_id',
        'user_name_snapshot',
        'department_name_snapshot',
        'work_center_name_snapshot',
        'note',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Professional Arabic label for the event type (spec §122).
     */
    public function getLabelArabicAttribute(): string
    {
        return match ($this->event_type) {
            'READY' => 'أصبحت العملية جاهزة للتنفيذ',
            'ASSIGNED' => 'تم إسناد العملية',
            'CLAIMED' => 'تم استلام المهمة',
            'REASSIGNED' => 'تمت إعادة إسناد العملية',
            'STARTED' => 'بدأ التنفيذ',
            'PROGRESS_RECORDED' => 'تم تسجيل تقدم',
            'BLOCKED' => 'تم إيقاف العملية',
            'BLOCK_RESOLVED' => 'تم حل العائق',
            'COMPLETED' => 'اكتملت العملية',
            'CANCELLED' => 'تم إلغاء العملية',
            'REWORK_CREATED' => 'تم إنشاء إعادة عمل',
            'REWORK_COMPLETED' => 'اكتملت إعادة العمل',
            default => $this->event_type,
        };
    }
}
