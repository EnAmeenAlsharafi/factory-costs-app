<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_number',
        'production_order_id',
        'production_order_operation_id',
        'affected_quantity',
        'detected_department_id',
        'responsible_department_id',
        'incident_type',
        'description',
        'severity',
        'disposition',
        'status',
        'detected_by_user_id',
        'decided_by_user_id',
        'decision_at',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'affected_quantity' => 'integer',
        'decision_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function operation()
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function detectedDepartment()
    {
        return $this->belongsTo(Department::class, 'detected_department_id');
    }

    public function responsibleDepartment()
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }

    public function detectedByUser()
    {
        return $this->belongsTo(User::class, 'detected_by_user_id');
    }

    public function decidedByUser()
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function reworkActions()
    {
        return $this->hasMany(ProductionReworkAction::class, 'quality_incident_id');
    }

    public function wasteRecords()
    {
        return $this->hasMany(ProductionWasteRecord::class, 'quality_incident_id');
    }
}
