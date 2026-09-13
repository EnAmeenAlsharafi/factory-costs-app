<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinishedGoodsReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'production_order_id',
        'customer_order_id',
        'warehouse_id',
        'quantity',
        'status',
        'receipt_date',
        'received_by_user_id',
        'created_by_user_id',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'receipt_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinishedGoodsMovement::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }

    public function getReceivedQuantityAttribute(): float
    {
        return (float) $this->quantity;
    }
}
