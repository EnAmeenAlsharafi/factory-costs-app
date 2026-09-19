<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'quotation_id',
        'customer_id',
        'sales_channel_id',
        'customer_reference',
        'external_order_reference',
        'order_date',
        'requested_delivery_date',
        'priority',
        'status',
        'payment_terms_type',
        'deposit_required_amount',
        'deposit_required_percent',
        'payment_due_date',
        'credit_days',
        'subtotal',
        'discount_total',
        'total_amount',
        'commercial_notes',
        'production_notes',
        'created_by_user_id',
        'production_reviewed_by_user_id',
        'production_reviewed_at',
        'production_approved_by_user_id',
        'production_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'requested_delivery_date' => 'date',
            'payment_due_date' => 'date',
            'credit_days' => 'integer',
            'deposit_required_amount' => 'decimal:2',
            'deposit_required_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'production_reviewed_at' => 'datetime',
            'production_approved_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function productionReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'production_reviewed_by_user_id');
    }

    public function productionApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'production_approved_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(CustomerOrderChange::class)->latest('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function paymentOverrides(): HasMany
    {
        return $this->hasMany(PaymentControlOverride::class)->latest('id');
    }

    public function getConfirmedPaidAmountAttribute(): float
    {
        return (float) $this->allocations()
            ->whereHas('payment', function ($q) {
                $q->where('status', 'CONFIRMED');
            })
            ->sum('allocated_amount');
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return max(0.00, (float) $this->total_amount - $this->confirmed_paid_amount);
    }

    public function getRequiredDepositAmountAttribute(): float
    {
        if ($this->deposit_required_amount !== null && (float) $this->deposit_required_amount > 0) {
            return (float) $this->deposit_required_amount;
        }

        if ($this->deposit_required_percent !== null && (float) $this->deposit_required_percent > 0) {
            return round((float) $this->total_amount * ((float) $this->deposit_required_percent / 100), 2);
        }

        return 0.00;
    }

    public function getIsDepositSatisfiedAttribute(): bool
    {
        $required = $this->required_deposit_amount;

        if ($required <= 0) {
            return true;
        }

        return $this->confirmed_paid_amount >= $required;
    }

    public function getPaymentStatusAttribute(): string
    {
        $total = (float) $this->total_amount;
        $paid = $this->confirmed_paid_amount;
        $outstanding = $this->outstanding_balance;
        $requiredDeposit = $this->required_deposit_amount;

        if ($this->status === 'CANCELLED' || $this->status === 'REJECTED') {
            return 'CANCELLED';
        }

        if ($paid >= $total && $total > 0) {
            return 'PAID';
        }

        if ($this->payment_due_date && $this->payment_due_date->isPast() && $outstanding > 0) {
            return 'OVERDUE';
        }

        if ($requiredDeposit > 0 && $paid < $requiredDeposit) {
            return 'DEPOSIT_PENDING';
        }

        if ($paid > 0 && $outstanding > 0) {
            return 'PARTIALLY_PAID';
        }

        if ($this->payment_terms_type === 'CREDIT' && $outstanding > 0) {
            return 'CREDIT';
        }

        return 'UNPAID';
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->lines()->sum('line_total') + $this->lines()->sum('discount_amount');
        $this->discount_total = $this->lines()->sum('discount_amount');
        $this->total_amount = $this->lines()->sum('line_total');
        $this->save();
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_reference', 'like', "%{$search}%")
                        ->orWhere('external_order_reference', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['priority'] ?? null, function ($q, $priority) {
                $q->where('priority', $priority);
            })
            ->when($filters['customer_id'] ?? null, function ($q, $customerId) {
                $q->where('customer_id', $customerId);
            })
            ->when($filters['sales_channel_id'] ?? null, function ($q, $channelId) {
                $q->where('sales_channel_id', $channelId);
            });
    }
}
