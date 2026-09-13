<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'customer_order_id',
        'delivery_order_id',
        'production_order_id',
        'customer_order_line_id',
        'quantity',
        'reason_code',
        'condition_code',
        'status',
        'reported_at',
        'received_at',
        'reported_by_user_id',
        'received_by_user_id',
        'quality_incident_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'reported_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function qualityIncident(): BelongsTo
    {
        return $this->belongsTo(QualityIncident::class);
    }

    public function getQuantityReturnedAttribute(): float
    {
        return (float) $this->quantity;
    }
}
