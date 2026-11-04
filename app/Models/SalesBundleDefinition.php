<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesBundleDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sales_sku',
        'match_name_normalized',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(SalesBundleComponent::class, 'bundle_definition_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
