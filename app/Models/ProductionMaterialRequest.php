<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionMaterialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'production_order_id',
        'requested_by_user_id',
        'requested_from_department_id',
        'warehouse_id',
        'request_date',
        'status',
        'notes',
        'reviewed_by_user_id',
        'reviewed_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'reviewed_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function requestedFromDepartment()
    {
        return $this->belongsTo(Department::class, 'requested_from_department_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(ProductionMaterialRequestLine::class, 'production_material_request_id');
    }

    public function materialIssues()
    {
        return $this->hasMany(MaterialIssue::class, 'production_material_request_id');
    }

    public function materialReturns()
    {
        return $this->hasMany(MaterialReturn::class, 'production_material_request_id');
    }
}
