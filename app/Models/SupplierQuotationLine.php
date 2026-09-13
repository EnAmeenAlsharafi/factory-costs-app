<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuotationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_quotation_id',
        'purchase_request_line_id',
        'material_id',
        'fabric_color_id',
        'quoted_quantity',
        'purchase_unit_id',
        'conversion_factor',
        'normalized_base_quantity',
        'unit_price',
        'line_total',
        'supplier_material_code',
        'lead_time_days',
        'notes',
    ];

    protected $casts = [
        'quoted_quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'normalized_base_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function supplierQuotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class);
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
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

    public function getBaseUnitEquivalentPriceAttribute(): float
    {
        $factor = (float) $this->conversion_factor;

        return $factor > 0 ? (float) $this->unit_price / $factor : (float) $this->unit_price;
    }
}
