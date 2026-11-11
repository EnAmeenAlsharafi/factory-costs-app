<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description',
        'sort_order',
        'is_production_department',
        'awards_points',
        'target_days',
        'warning_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_production_department' => 'boolean',
            'awards_points' => 'boolean',
            'target_days' => 'integer',
            'warning_percent' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Factory users assigned to this operational department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Roles defined specifically for this department.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Scope to active departments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to production departments.
     */
    public function scopeProduction(Builder $query): Builder
    {
        return $query->where('is_production_department', true);
    }
}
