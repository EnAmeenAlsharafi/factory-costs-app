<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRoutingDependency extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'depends_on_operation_id',
        'dependency_type',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionRoutingOperation::class, 'operation_id');
    }

    public function dependsOnOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionRoutingOperation::class, 'depends_on_operation_id');
    }
}
