<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionMaterialRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'manufacturing_recipe_version_id',
        'manufacturing_recipe_item_id',
        'material_id',
        'semi_finished_component_id',
        'required_quantity_per_unit',
        'waste_percentage',
        'planned_quantity_per_unit',
        'production_quantity',
        'total_planned_quantity',
        'unit_id',
        'material_name_snapshot',
        'notes',
    ];

    protected $casts = [
        'required_quantity_per_unit' => 'decimal:4',
        'waste_percentage' => 'decimal:2',
        'planned_quantity_per_unit' => 'decimal:4',
        'total_planned_quantity' => 'decimal:4',
        'production_quantity' => 'integer',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function recipeVersion()
    {
        return $this->belongsTo(ManufacturingRecipeVersion::class, 'manufacturing_recipe_version_id');
    }

    public function recipeItem()
    {
        return $this->belongsTo(ManufacturingRecipeItem::class, 'manufacturing_recipe_item_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function semiFinishedComponent()
    {
        return $this->belongsTo(SemiFinishedComponent::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function requestLines()
    {
        return $this->hasMany(ProductionMaterialRequestLine::class, 'production_material_requirement_id');
    }
}
