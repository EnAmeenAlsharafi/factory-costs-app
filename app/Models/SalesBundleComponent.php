<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBundleComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_definition_id',
        'component_type',
        'product_model_id',
        'product_configuration_id',
        'material_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function bundleDefinition(): BelongsTo
    {
        return $this->belongsTo(SalesBundleDefinition::class, 'bundle_definition_id');
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
}
