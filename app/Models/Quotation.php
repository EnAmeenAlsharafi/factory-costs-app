<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'sales_channel_id',
        'quotation_date',
        'valid_until',
        'currency_code',
        'status',
        'subtotal',
        'discount_total',
        'total_amount',
        'notes',
        'commercial_notes',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'converted_to_order_at',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'converted_to_order_at' => 'datetime',
        ];
    }

    /**
     * Customer relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Sales Channel.
     */
    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    /**
     * Creator User.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Approving User.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Quotation lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    /**
     * Converted Customer Order (if converted).
     */
    public function customerOrder(): HasOne
    {
        return $this->hasOne(CustomerOrder::class);
    }

    /**
     * Recalculate quotation totals from lines.
     */
    public function recalculateTotals(): void
    {
        $this->subtotal = $this->lines()->sum('line_total') + $this->lines()->sum('discount_amount');
        $this->discount_total = $this->lines()->sum('discount_amount');
        $this->total_amount = $this->lines()->sum('line_total');
        $this->save();
    }

    /**
     * Filter scope.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('quotation_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['customer_id'] ?? null, function ($q, $customerId) {
                $q->where('customer_id', $customerId);
            });
    }
}
