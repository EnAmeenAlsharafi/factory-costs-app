<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_id',
        'customer_order_line_id',
        'production_order_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
