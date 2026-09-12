<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductionRoutingOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_routing_id',
        'work_center_id',
        'operation_code',
        'name_ar',
        'sequence_number',
        'branch_key',
        'is_parallel',
        'is_required',
    ];

    protected $casts = [
        'sequence_number' => 'integer',
        'is_parallel' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(ProductionRouting::class, 'production_routing_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductionRoutingOperation::class,
            'production_routing_dependencies',
            'operation_id',
            'depends_on_operation_id'
        )->withTimestamps();
    }

    public function dependentOperations(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductionRoutingOperation::class,
            'production_routing_dependencies',
            'depends_on_operation_id',
            'operation_id'
        )->withTimestamps();
    }
}
