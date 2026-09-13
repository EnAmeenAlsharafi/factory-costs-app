<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaterialReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_receipt_id',
        'purchase_order_line_id',
        'material_id',
        'fabric_color_id',
        'quantity_received',
        'purchase_unit_id',
        'conversion_factor',
        'base_quantity',
        'base_unit_id',
        'unit_cost_purchase',
        'total_cost',
        'unit_cost_base',
        'supplier_material_code',
        'quality_note',
        'lot_reference',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'conversion_factor' => 'decimal:6',
        'base_quantity' => 'decimal:4',
        'unit_cost_purchase' => 'decimal:6',
        'total_cost' => 'decimal:4',
        'unit_cost_base' => 'decimal:6',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class, 'fabric_color_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_unit_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function lot(): HasOne
    {
        return $this->hasOne(InventoryLot::class, 'receipt_line_id');
    }
}
