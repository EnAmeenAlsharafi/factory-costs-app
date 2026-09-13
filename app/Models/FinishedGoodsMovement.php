<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinishedGoodsMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_number',
        'production_order_id',
        'customer_order_id',
        'warehouse_id',
        'finished_goods_receipt_id',
        'delivery_order_id',
        'delivery_order_line_id',
        'customer_return_id',
        'movement_type',
        'direction',
        'quantity',
        'occurred_at',
        'performed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'occurred_at' => 'datetime',
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

    public function finishedGoodsReceipt(): BelongsTo
    {
        return $this->belongsTo(FinishedGoodsReceipt::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function deliveryOrderLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderLine::class);
    }

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
