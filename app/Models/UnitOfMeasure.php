<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use HasFactory;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'symbol',
        'unit_type',
        'allows_decimal',
        'decimal_precision',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allows_decimal' => 'boolean',
            'decimal_precision' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Generic conversions from this unit.
     */
    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    /**
     * Generic conversions to this unit.
     */
    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }

    /**
     * Scope to active units.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
