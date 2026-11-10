<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of beds that take a similar effort to make (barrier bed base 0.5, padded bed 1.2, ...).
 * Each finished piece in a department that awards points earns the worker this many points.
 */
class ProductionPointCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name_ar', 'points', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name_ar');
    }

    public function productModels(): HasMany
    {
        return $this->hasMany(ProductModel::class, 'point_category_id');
    }

    /**
     * «سرير مضخم — 1.2 نقطة».
     */
    public function getLabelAttribute(): string
    {
        return $this->name_ar.' — '.rtrim(rtrim(number_format((float) $this->points, 2, '.', ''), '0'), '.').' نقطة';
    }
}
