<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to the stock a supplier holds for us (signed quantity).
 */
class SupplierStockMovement extends Model
{
    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'OPENING' => 'رصيد افتتاحي',
        'PRODUCED' => 'إنتاج لدى المورد',
        'DELIVERED' => 'توريد لنا',
        'ADJUSTMENT' => 'تسوية',
    ];

    protected $fillable = [
        'material_id',
        'supplier_id',
        'movement_type',
        'quantity',
        'supplier_production_request_id',
        'purchase_order_id',
        'material_receipt_id',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
