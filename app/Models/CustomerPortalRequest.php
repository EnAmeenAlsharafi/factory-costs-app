<?php

namespace App\Models;

use Database\Factories\CustomerPortalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPortalRequest extends Model
{
    /** @use HasFactory<CustomerPortalRequestFactory> */
    use HasFactory;

    public const STATUSES = ['DRAFT' => 'مسودة', 'SUBMITTED' => 'بانتظار مراجعة خدمة العملاء', 'OFFERED' => 'بانتظار تأكيدك للعرض', 'CHANGES_REQUESTED' => 'بحاجة إلى تعديل', 'CONFIRMED' => 'مؤكد ومحال للتنفيذ'];

    public const PAYMENT_TERMS = ['FULL_BEFORE_PRODUCTION' => 'سداد كامل قبل الإنتاج', 'DEPOSIT_AND_BALANCE' => 'عربون ثم المبلغ المتبقي', 'CASH_ON_DELIVERY' => 'الدفع عند التسليم', 'CREDIT' => 'بيع آجل', 'CUSTOM' => 'شروط متفق عليها'];

    protected $fillable = ['customer_id', 'created_by_user_id', 'customer_order_id', 'status', 'revision', 'customer_reference', 'requested_delivery_date', 'promised_delivery_date', 'delivery_city', 'delivery_address', 'customer_notes', 'public_response', 'change_note', 'lines', 'offer_total', 'payment_terms_type', 'deposit_required_amount', 'payment_due_date', 'submitted_at', 'offered_at', 'confirmed_at'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'revision' => 'integer', 'offer_total' => 'decimal:2', 'deposit_required_amount' => 'decimal:2', 'requested_delivery_date' => 'date', 'promised_delivery_date' => 'date', 'payment_due_date' => 'date', 'submitted_at' => 'datetime', 'offered_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerPortalEvent::class)->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
