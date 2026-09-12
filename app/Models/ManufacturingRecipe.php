<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManufacturingRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_code',
        'target_type',
        'product_configuration_id',
        'semi_finished_component_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Target Product Configuration (if target_type = PRODUCT_CONFIGURATION).
     */
    public function productConfiguration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class);
    }

    /**
     * Target Semi-Finished Component (if target_type = SEMI_FINISHED_COMPONENT).
     */
    public function semiFinishedComponent(): BelongsTo
    {
        return $this->belongsTo(SemiFinishedComponent::class);
    }

    /**
     * Recipe versions.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ManufacturingRecipeVersion::class, 'manufacturing_recipe_id')->orderByDesc('version_number');
    }

    /**
     * Current APPROVED version.
     */
    public function currentApprovedVersion(): HasOne
    {
        return $this->hasOne(ManufacturingRecipeVersion::class, 'manufacturing_recipe_id')
            ->where('status', 'APPROVED')
            ->latest('id');
    }

    /**
     * Scope active recipes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
