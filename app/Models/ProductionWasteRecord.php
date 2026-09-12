<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionWasteRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'waste_number',
        'production_order_id',
        'production_order_operation_id',
        'quality_incident_id',
        'material_issue_line_id',
        'inventory_lot_id',
        'material_id',
        'fabric_color_id',
        'quantity',
        'unit_id',
        'unit_cost',
        'total_cost',
        'waste_reason_id',
        'detected_department_id',
        'responsible_department_id',
        'recorded_by_user_id',
        'approved_by_user_id',
        'notes',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function operation()
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function qualityIncident()
    {
        return $this->belongsTo(QualityIncident::class);
    }

    public function issueLine()
    {
        return $this->belongsTo(MaterialIssueLine::class, 'material_issue_line_id');
    }

    public function lot()
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor()
    {
        return $this->belongsTo(FabricColor::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function wasteReason()
    {
        return $this->belongsTo(ProductionWasteReason::class, 'waste_reason_id');
    }

    public function detectedDepartment()
    {
        return $this->belongsTo(Department::class, 'detected_department_id');
    }

    public function responsibleDepartment()
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }

    public function recordedByUser()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
