<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialIssueLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_issue_id',
        'material_id',
        'fabric_color_id',
        'inventory_lot_id',
        'requested_quantity',
        'issued_quantity',
        'base_unit_id',
        'unit_cost',
        'total_cost',
        'notes',
        'production_material_request_line_id',
        'request_reason',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:4',
        'issued_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:4',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialRequestLine::class, 'production_material_request_line_id');
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

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(MaterialReturn::class, 'original_issue_line_id');
    }
}
