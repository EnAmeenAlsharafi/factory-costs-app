<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionReworkAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'rework_number',
        'quality_incident_id',
        'production_order_id',
        'source_operation_id',
        'target_operation_id',
        'action_type',
        'quantity',
        'status',
        'assigned_department_id',
        'authorized_by_user_id',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function qualityIncident()
    {
        return $this->belongsTo(QualityIncident::class);
    }

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function sourceOperation()
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'source_operation_id');
    }

    public function targetOperation()
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'target_operation_id');
    }

    public function assignedDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function authorizedByUser()
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }
}
