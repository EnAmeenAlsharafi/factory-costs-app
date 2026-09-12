<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'product_model_id',
        'product_configuration_id',
        'customer_product_alias_id',
        'custom_design',
        'custom_design_name',
        'requested_width_cm',
        'requested_length_cm',
        'reference_width_cm',
        'reference_length_cm',
        'has_storage',
        'fabric_material_id',
        'fabric_color_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
        'notes',
        'production_notes',
    ];

    protected function casts(): array
    {
        return [
            'custom_design' => 'boolean',
            'has_storage' => 'boolean',
            'requested_width_cm' => 'decimal:2',
            'requested_length_cm' => 'decimal:2',
            'reference_width_cm' => 'decimal:2',
            'reference_length_cm' => 'decimal:2',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function productConfiguration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class);
    }

    public function customerProductAlias(): BelongsTo
    {
        return $this->belongsTo(CustomerProductAlias::class);
    }

    public function fabricMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'fabric_material_id');
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class, 'fabric_color_id');
    }

    /**
     * Display name for line item.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->custom_design) {
            return $this->custom_design_name ?: 'تصميم خاص للعميل';
        }

        if ($this->customerProductAlias) {
            return $this->customerProductAlias->customer_product_name;
        }

        return $this->productModel ? $this->productModel->name_ar : 'منتج غير محدد';
    }
}
