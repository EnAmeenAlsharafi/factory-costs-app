<?php

namespace App\Models;

use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'customer_type_id',
        'default_sales_channel_id',
        'name',
        'commercial_name',
        'mobile',
        'phone',
        'email',
        'tax_number',
        'commercial_registration',
        'city',
        'address',
        'contact_person',
        'notes',
        'is_active',
        'is_credit_customer',
        'credit_limit',
        'opening_balance',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_credit_customer' => 'boolean',
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    /**
     * Customer classification type.
     */
    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function defaultSalesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'default_sales_channel_id');
    }

    public function orders()
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function creditProfile()
    {
        return $this->hasOne(CustomerCreditProfile::class);
    }

    public function getUnallocatedCreditAttribute(): float
    {
        return (float) $this->payments()
            ->where('status', 'CONFIRMED')
            ->get()
            ->sum('unallocated_amount');
    }

    public function getTotalOutstandingBalanceAttribute(): float
    {
        return (float) $this->orders()
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->get()
            ->sum('outstanding_balance');
    }

    public function getCurrentCreditExposureAttribute(): float
    {
        return $this->total_outstanding_balance;
    }

    /**
     * Generate the next stable sequential customer code (e.g. CUS-000001).
     */
    public static function generateNextCode(): string
    {
        return DocumentNumberService::generateCustomerCode();
    }

    /**
     * Scope to active customers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query filters for search and classification.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('commercial_name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['customer_type_id']) && $filters['customer_type_id'] !== '', function ($q) use ($filters) {
                $q->where('customer_type_id', $filters['customer_type_id']);
            })
            ->when(isset($filters['sales_channel_id']) && $filters['sales_channel_id'] !== '', function ($q) use ($filters) {
                $q->where('default_sales_channel_id', $filters['sales_channel_id']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('is_active', $filters['status'] === 'active' || $filters['status'] === '1');
            })
            ->when(isset($filters['credit']) && $filters['credit'] !== '', function ($q) use ($filters) {
                $q->where('is_credit_customer', $filters['credit'] === 'credit' || $filters['credit'] === '1');
            });
    }
}
