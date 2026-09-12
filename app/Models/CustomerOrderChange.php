<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrderChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_order_id',
        'customer_order_line_id',
        'field_name',
        'old_value',
        'new_value',
        'change_type',
        'requested_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'occurred_after_production_approval',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_after_production_approval' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
