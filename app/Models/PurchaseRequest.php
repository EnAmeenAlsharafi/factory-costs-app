<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'request_date',
        'requested_by_user_id',
        'department_id',
        'warehouse_id',
        'source_type',
        'source_reference_id',
        'priority',
        'required_by_date',
        'status',
        'justification',
        'notes',
        'submitted_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'request_date' => 'date',
        'required_by_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(PurchaseRfq::class);
    }
}
