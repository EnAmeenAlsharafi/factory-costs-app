<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_number',
        'supplier_id',
        'purchase_request_id',
        'supplier_quotation_id',
        'warehouse_id',
        'order_date',
        'expected_delivery_date',
        'currency_code',
        'status',
        'supplier_reference',
        'payment_terms',
        'delivery_terms',
        'subtotal',
        'discount_amount',
        'shipping_amount',
        'other_charges',
        'total_amount',
        'notes',
        'close_reason',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'sent_at',
        'closed_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'closed_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'other_charges' => 'decimal:4',
        'total_amount' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function supplierQuotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function materialReceipts(): HasMany
    {
        return $this->hasMany(MaterialReceipt::class);
    }
}
