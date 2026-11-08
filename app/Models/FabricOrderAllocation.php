<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The meters of a fabric purchase order line that are meant for one production order (one bed batch).
 */
class FabricOrderAllocation extends Model
{
    protected $fillable = [
        'purchase_order_line_id',
        'production_order_id',
        'meters',
    ];

    protected function casts(): array
    {
        return ['meters' => 'decimal:2'];
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
