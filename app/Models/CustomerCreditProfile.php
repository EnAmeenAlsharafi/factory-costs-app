<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'credit_enabled',
        'credit_limit',
        'credit_days',
        'warning_threshold_percent',
        'hold_when_exceeded',
        'approved_by_user_id',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_enabled' => 'boolean',
            'hold_when_exceeded' => 'boolean',
            'credit_limit' => 'decimal:2',
            'warning_threshold_percent' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
