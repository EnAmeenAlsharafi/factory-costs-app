<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'purchase_request_line_id',
        'supplier_quotation_line_id',
        'material_id',
        'fabric_color_id',
        'ordered_quantity',
        'purchase_unit_id',
        'conversion_factor',
        'ordered_base_quantity',
        'unit_price',
        'line_total',
        'received_base_quantity',
        'notes',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'ordered_base_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
        'received_base_quantity' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
    }

    public function supplierQuotationLine(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotationLine::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_unit_id');
    }

    public function materialReceiptLines(): HasMany
    {
        return $this->hasMany(MaterialReceiptLine::class);
    }

    public function getRemainingBaseQuantityAttribute(): float
    {
        return max(0.0, (float) $this->ordered_base_quantity - (float) $this->received_base_quantity);
    }

    public function getBaseUnitEquivalentPriceAttribute(): float
    {
        $factor = (float) $this->conversion_factor;

        return $factor > 0 ? (float) $this->unit_price / $factor : (float) $this->unit_price;
    }
}
