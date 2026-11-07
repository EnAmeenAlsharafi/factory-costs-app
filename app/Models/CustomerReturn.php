<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturn extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'REPORTED' => 'مُبلغ عنه',
        'APPROVED' => 'معتمد بانتظار الاستلام',
        'REJECTED' => 'مرفوض',
        'RECEIVED' => 'مستلم في منطقة الفحص',
        'LINKED_TO_QUALITY' => 'مرتبط بحالة جودة',
        'UNDER_REPAIR' => 'قيد الإصلاح',
        'INSPECTED' => 'تم الفحص بانتظار الحل',
        'RESOLVED' => 'مغلق',
    ];

    /**
     * What happens to the returned goods after inspection.
     *
     * @var array<string, string>
     */
    public const INSPECTION_DECISIONS = [
        'RESTOCK' => 'سليم: إعادة للمخزون المتاح',
        'REPAIR' => 'يحتاج إصلاح ثم يعود للمخزون',
        'SCRAP' => 'تالف: إتلاف',
    ];

    /**
     * How the case was closed for the customer.
     *
     * @var array<string, string>
     */
    public const RESOLUTIONS = [
        'REPLACEMENT' => 'استبدال بمنتج آخر',
        'REPAIRED_RETURNED' => 'إصلاح وإعادة للعميل',
        'REFUND' => 'استرداد المبلغ',
        'CREDIT_NOTE' => 'رصيد دائن للعميل',
        'NO_ACTION' => 'بدون إجراء مالي',
    ];

    protected $fillable = [
        'return_number',
        'customer_order_id',
        'delivery_order_id',
        'production_order_id',
        'customer_order_line_id',
        'quantity',
        'reason_code',
        'condition_code',
        'status',
        'reported_at',
        'received_at',
        'reported_by_user_id',
        'received_by_user_id',
        'quality_incident_id',
        'notes',
        'approved_at',
        'approved_by_user_id',
        'inspection_decision',
        'inspected_at',
        'inspected_by_user_id',
        'resolution_code',
        'resolution_notes',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'reported_at' => 'datetime',
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
        'inspected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function inspectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function qualityIncident(): BelongsTo
    {
        return $this->belongsTo(QualityIncident::class);
    }

    public function getQuantityReturnedAttribute(): float
    {
        return (float) $this->quantity;
    }
}
