<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isWood(): bool
    {
        return $this->code === 'WOOD';
    }

    public function isFoam(): bool
    {
        return $this->code === 'FOAM';
    }

    public function isFabric(): bool
    {
        return $this->code === 'FABRIC';
    }
}
