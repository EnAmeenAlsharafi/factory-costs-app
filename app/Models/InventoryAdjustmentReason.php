<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustmentReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'reason_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
