<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityIncident extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'WRONG_DIMENSION' => 'خطأ في المقاسات',
        'WRONG_FABRIC' => 'خطأ في نوع القماش',
        'WRONG_COLOR' => 'خطأ في اللون',
        'MATERIAL_DAMAGE' => 'تلف أو عيب خامة',
        'WORKMANSHIP_DEFECT' => 'عيب تصنيع',
        'LOADING_DAMAGE' => 'تلف مناولة وتحميل',
        'MISSING_COMPONENT' => 'نقص مكوّن',
        'ASSEMBLY_ERROR' => 'خطأ تجميع',
        'TRANSPORT_DAMAGE' => 'تلف أثناء النقل',
        'OTHER' => 'أخرى',
    ];

    /**
     * Severity => [Arabic label, tone].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SEVERITY_LABELS = [
        'LOW' => ['منخفضة', 'info'],
        'MEDIUM' => ['متوسطة', 'warning'],
        'HIGH' => ['عالية', 'danger'],
        'CRITICAL' => ['حرجة', 'dark'],
    ];

    /**
     * @var array<string, string>
     */
    public const DISPOSITION_LABELS = [
        'REPAIR' => 'إصلاح بسيط',
        'REWORK' => 'إعادة تصنيع',
        'REMANUFACTURE' => 'إعادة إنتاج كاملة',
        'SCRAP' => 'إتلاف',
        'ACCEPT_AS_IS' => 'قبول بحالته',
    ];

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

    /**
     * Scope to incidents a department-restricted user may see: detected in, caused by, or on an operation of their department.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isDepartmentRestricted()) {
            return $query;
        }

        $departmentId = $user->productionDepartmentId();

        return $query->where(fn (Builder $q) => $q
            ->where('detected_department_id', $departmentId)
            ->orWhere('responsible_department_id', $departmentId)
            ->orWhereHas('operation', fn (Builder $op) => $op->visibleTo($user)));
    }
}
