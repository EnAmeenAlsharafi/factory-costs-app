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
        'line_type',
        'material_id',
        'prepared_by_user_id',
        'prepared_at',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'prepared_at' => 'datetime',
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

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }
}
