<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The quantity of a supply purchase order line that is meant for one customer order line.
 */
class ReadyProductOrderAllocation extends Model
{
    protected $fillable = [
        'purchase_order_line_id',
        'customer_order_line_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }
}
