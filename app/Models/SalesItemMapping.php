<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesItemMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_channel_id',
        'match_type',
        'match_value',
        'target_type',
        'product_model_id',
        'product_configuration_id',
        'material_id',
        'bundle_definition_id',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function productConfiguration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function bundleDefinition(): BelongsTo
    {
        return $this->belongsTo(SalesBundleDefinition::class, 'bundle_definition_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
