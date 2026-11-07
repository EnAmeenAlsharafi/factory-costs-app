<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use HasFactory;

    /**
     * Why a delivery could not be completed at the customer's door.
     *
     * @var array<string, string>
     */
    public const FAILURE_REASONS = [
        'CUSTOMER_UNAVAILABLE' => 'العميل غير متواجد / لا يرد',
        'CUSTOMER_POSTPONED' => 'العميل طلب التأجيل',
        'WRONG_ADDRESS' => 'تعذر الوصول للعنوان',
        'REFUSED_DEFECT' => 'رفض الاستلام: عيب ظاهر في المنتج',
        'REFUSED_WRONG_SPEC' => 'رفض الاستلام: مقاس أو لون أو موديل خطأ',
        'REFUSED_CHANGED_MIND' => 'رفض الاستلام: تراجع العميل عن الطلب',
        'REFUSED_PAYMENT' => 'رفض الاستلام: خلاف على الدفع',
        'TRANSPORT_DAMAGE' => 'تلف أثناء النقل',
        'VEHICLE_ISSUE' => 'مشكلة في المركبة أو الفريق',
        'OTHER' => 'سبب آخر',
    ];

    /**
     * Reasons whose returned goods must be inspected before they can be delivered again.
     *
     * @var list<string>
     */
    public const QUARANTINE_REASONS = ['REFUSED_DEFECT', 'REFUSED_WRONG_SPEC', 'TRANSPORT_DAMAGE'];

    /**
     * Reasons that are a product-quality problem and open a quality incident automatically.
     *
     * @var list<string>
     */
    public const QUALITY_REASONS = ['REFUSED_DEFECT', 'TRANSPORT_DAMAGE'];

    /**
     * Where the goods of a failed delivery are now.
     *
     * @var array<string, string>
     */
    public const GOODS_LOCATIONS = [
        'FACTORY' => 'عادت لمخزن المنتجات الجاهزة',
        'QUARANTINE' => 'في منطقة الفحص بانتظار قرار',
        'WITH_DRIVER' => 'ما زالت مع فريق التوصيل',
    ];

    protected $fillable = [
        'delivery_number',
        'customer_order_id',
        'customer_id',
        'sales_channel_id',
        'scheduled_delivery_date',
        'scheduled_time_notes',
        'status',
        'assigned_user_id',
        'assigned_at',
        'assigned_by_user_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'city_snapshot',
        'district_snapshot',
        'delivery_address_snapshot',
        'location_notes',
        'installation_required',
        'delivery_notes',
        'installation_notes',
        'created_by_user_id',
        'dispatched_by_user_id',
        'dispatched_at',
        'delivered_at',
        'installed_at',
        'failure_reason_code',
        'goods_location',
        'successor_delivery_order_id',
        'recipient_name',
    ];

    protected $casts = [
        'scheduled_delivery_date' => 'date',
        'installation_required' => 'boolean',
        'assigned_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'installed_at' => 'datetime',
    ];

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at', 'desc');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinishedGoodsMovement::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    /**
     * The delivery order created when this failed delivery was rescheduled.
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'successor_delivery_order_id');
    }

    /**
     * A failed or postponed delivery still waiting for a management decision.
     */
    public function isAwaitingDecision(): bool
    {
        return in_array($this->status, ['FAILED', 'RESCHEDULED'], true) && $this->successor_delivery_order_id === null;
    }

    public function failureReasonLabel(): ?string
    {
        return $this->failure_reason_code ? (self::FAILURE_REASONS[$this->failure_reason_code] ?? $this->failure_reason_code) : null;
    }

    public function isDispatched(): bool
    {
        return in_array($this->status, ['OUT_FOR_DELIVERY', 'DELIVERED', 'INSTALLATION_COMPLETED'], true);
    }

    public function isCompleted(): bool
    {
        if ($this->installation_required) {
            return $this->status === 'INSTALLATION_COMPLETED';
        }

        return in_array($this->status, ['DELIVERED', 'INSTALLATION_COMPLETED'], true);
    }
}
