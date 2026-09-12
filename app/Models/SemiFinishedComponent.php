<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SemiFinishedComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_code',
        'name_ar',
        'name_en',
        'width_cm',
        'length_cm',
        'has_storage',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'width_cm' => 'decimal:2',
            'length_cm' => 'decimal:2',
            'has_storage' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Target recipe for this semi-finished component.
     */
    public function recipe(): HasOne
    {
        return $this->hasOne(ManufacturingRecipe::class, 'semi_finished_component_id');
    }

    /**
     * Recipes that consume this semi-finished component.
     */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(ManufacturingRecipeItem::class, 'semi_finished_component_id');
    }

    /**
     * Scope active components.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
