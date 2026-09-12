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
