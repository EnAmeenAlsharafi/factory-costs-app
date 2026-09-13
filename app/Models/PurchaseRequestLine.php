<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequestLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'material_id',
        'fabric_color_id',
        'requested_quantity',
        'base_unit_id',
        'preferred_purchase_unit_id',
        'required_by_date',
        'estimated_unit_cost',
        'estimated_total_cost',
        'preferred_supplier_id',
        'justification',
        'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:4',
        'estimated_unit_cost' => 'decimal:4',
        'estimated_total_cost' => 'decimal:4',
        'required_by_date' => 'date',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function preferredPurchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'preferred_purchase_unit_id');
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function poLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }
}
