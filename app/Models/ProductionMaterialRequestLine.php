<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionMaterialRequestLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_material_request_id',
        'production_material_requirement_id',
        'material_id',
        'fabric_color_id',
        'requested_quantity',
        'approved_quantity',
        'issued_quantity',
        'base_unit_id',
        'request_reason',
        'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:4',
        'approved_quantity' => 'decimal:4',
        'issued_quantity' => 'decimal:4',
    ];

    public function request()
    {
        return $this->belongsTo(ProductionMaterialRequest::class, 'production_material_request_id');
    }

    public function requirement()
    {
        return $this->belongsTo(ProductionMaterialRequirement::class, 'production_material_requirement_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor()
    {
        return $this->belongsTo(FabricColor::class);
    }

    public function baseUnit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function issueLines()
    {
        return $this->hasMany(MaterialIssueLine::class, 'production_material_request_line_id');
    }
}
