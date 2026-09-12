<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufacturingRecipeVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturing_recipe_id',
        'version_number',
        'status',
        'effective_from',
        'effective_to',
        'notes',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Parent recipe.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ManufacturingRecipe::class, 'manufacturing_recipe_id');
    }

    /**
     * Approving User.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Items (materials & components) in this version.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ManufacturingRecipeItem::class, 'recipe_version_id')->orderBy('sort_order')->orderBy('id');
    }
}
