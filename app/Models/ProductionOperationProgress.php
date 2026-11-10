<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationProgress extends Model
{
    use HasFactory;

    protected $table = 'production_operation_progress';

    protected $fillable = [
        'production_order_operation_id',
        'event_type',
        'quantity',
        'previous_completed_quantity',
        'new_completed_quantity',
        'user_id',
        'worker_user_id',
        'point_category_id',
        'points_per_unit',
        'points',
        'notes',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_completed_quantity' => 'integer',
        'new_completed_quantity' => 'integer',
        'occurred_at' => 'datetime',
        'points_per_unit' => 'decimal:2',
        'points' => 'decimal:2',
    ];

    /**
     * The employee credited with the work (and its points).
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function pointCategory(): BelongsTo
    {
        return $this->belongsTo(ProductionPointCategory::class, 'point_category_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
