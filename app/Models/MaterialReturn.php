<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'warehouse_id',
        'return_date',
        'department_id',
        'status',
        'returned_by_user_id',
        'received_by_user_id',
        'created_by_user_id',
        'posted_at',
        'notes',
        'production_order_id',
        'production_material_request_id',
    ];

    protected $casts = [
        'return_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function productionMaterialRequest(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialRequest::class, 'production_material_request_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialReturnLine::class, 'material_return_id');
    }

    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->lines->sum('total_cost');
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->lines->sum('total_cost');
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }
}
