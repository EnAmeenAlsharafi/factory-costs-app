<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'code',
        'name_ar',
        'description',
        'is_active',
        'sort_order',
        'allows_parallel_work',
        'daily_capacity',
        'capacity_unit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allows_parallel_work' => 'boolean',
        'daily_capacity' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function routingOperations(): HasMany
    {
        return $this->hasMany(ProductionRoutingOperation::class);
    }

    public function orderOperations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class);
    }
}
