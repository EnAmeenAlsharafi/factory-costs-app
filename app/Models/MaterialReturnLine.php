<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReturnLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_return_id',
        'material_id',
        'fabric_color_id',
        'inventory_lot_id',
        'original_issue_line_id',
        'returned_quantity',
        'base_unit_id',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'returned_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:4',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(MaterialReturn::class, 'material_return_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class, 'fabric_color_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    public function originalIssueLine(): BelongsTo
    {
        return $this->belongsTo(MaterialIssueLine::class, 'original_issue_line_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function getUnitAttribute()
    {
        return $this->baseUnit;
    }
}
