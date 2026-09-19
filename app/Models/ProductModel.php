<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_code',
        'name_ar',
        'name_en',
        'description',
        'reference_image_path',
        'design_notes',
        'is_custom_template',
        'requires_fabric_selection',
        'is_active',
    ];

    protected $casts = [
        'is_custom_template' => 'boolean',
        'requires_fabric_selection' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function configurations(): HasMany
    {
        return $this->hasMany(ProductConfiguration::class, 'product_model_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CustomerProductAlias::class, 'product_model_id');
    }

    public function defaultAliasForCustomer(int $customerId): ?CustomerProductAlias
    {
        return $this->aliases()
            ->where('customer_id', $customerId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->reference_image_path) {
            return null;
        }

        if (str_starts_with($this->reference_image_path, 'http://') || str_starts_with($this->reference_image_path, 'https://')) {
            return $this->reference_image_path;
        }

        return asset('storage/'.$this->reference_image_path);
    }
}
