<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_order_id',
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
        'fabric_supplier_id',
        'fabric_material_id',
        'fabric_color_id',
        'fabric_color_code',
        'fabric_notes',
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

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
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

    public function fabricSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'fabric_supplier_id');
    }

    public function fabricMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'fabric_material_id');
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class, 'fabric_color_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function getRecipeVersionAttribute(): ?ManufacturingRecipeVersion
    {
        if (! $this->product_configuration_id) {
            return null;
        }

        $recipe = ManufacturingRecipe::where('product_configuration_id', $this->product_configuration_id)
            ->with(['currentApprovedVersion.recipe'])
            ->first();

        return $recipe?->currentApprovedVersion;
    }

    /**
     * Display name for order line item.
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

    /**
     * Check if an APPROVED manufacturing recipe is available for this line.
     */
    public function getRecipeStatusAttribute(): string
    {
        if ($this->custom_design) {
            return 'CUSTOM_DESIGN';
        }

        if ($this->product_configuration_id) {
            $hasApprovedRecipe = ManufacturingRecipe::where('product_configuration_id', $this->product_configuration_id)
                ->whereHas('currentApprovedVersion')
                ->exists();

            return $hasApprovedRecipe ? 'AVAILABLE' : 'NO_RECIPE';
        }

        return 'NO_RECIPE';
    }
}
