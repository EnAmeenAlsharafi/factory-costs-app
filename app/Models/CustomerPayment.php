<?php

namespace App\Models;

use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'customer_id',
        'payment_date',
        'amount',
        'currency_code',
        'payment_method',
        'reference_number',
        'bank_reference',
        'status',
        'received_by_user_id',
        'created_by_user_id',
        'confirmed_by_user_id',
        'confirmed_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public static function generateNextNumber(): string
    {
        return DocumentNumberService::generateCustomerPaymentNumber();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerPaymentEvent::class)->latest('id');
    }

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('allocated_amount');
    }

    public function getUnallocatedAmountAttribute(): float
    {
        if ($this->status !== 'CONFIRMED') {
            return 0.00;
        }

        return max(0.00, (float) $this->amount - $this->allocated_amount);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'CONFIRMED');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('bank_reference', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cust) use ($search) {
                            $cust->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                });
            })
            ->when(isset($filters['customer_id']) && $filters['customer_id'] !== '', function ($q) use ($filters) {
                $q->where('customer_id', $filters['customer_id']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->when(isset($filters['payment_method']) && $filters['payment_method'] !== '', function ($q) use ($filters) {
                $q->where('payment_method', $filters['payment_method']);
            });
    }
}
