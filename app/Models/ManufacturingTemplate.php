<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufacturingTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_code',
        'name_ar',
        'name_en',
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
     * Items in this template.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ManufacturingTemplateItem::class, 'manufacturing_template_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Scope active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
