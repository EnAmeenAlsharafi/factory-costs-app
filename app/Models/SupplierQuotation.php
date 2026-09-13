<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_quotation_number',
        'purchase_rfq_id',
        'supplier_id',
        'supplier_reference',
        'quotation_date',
        'valid_until',
        'currency_code',
        'payment_terms',
        'delivery_terms',
        'lead_time_days',
        'status',
        'subtotal',
        'discount_amount',
        'shipping_amount',
        'other_charges',
        'total_amount',
        'notes',
        'created_by_user_id',
        'selected_by_user_id',
        'selected_at',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'selected_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'other_charges' => 'decimal:4',
        'total_amount' => 'decimal:4',
    ];

    public function purchaseRfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function selectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierQuotationLine::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
