<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_number',
        'customer_order_id',
        'customer_order_line_id',
        'product_model_id',
        'product_configuration_id',
        'manufacturing_recipe_version_id',
        'customer_product_alias_id',
        'production_routing_id',
        'is_custom_design',
        'custom_design_name',
        'requested_width_cm',
        'requested_length_cm',
        'reference_width_cm',
        'reference_length_cm',
        'has_storage',
        'fabric_material_id',
        'fabric_color_id',
        'ordered_quantity',
        'released_quantity',
        'completed_quantity',
        'priority',
        'status',
        'planned_start_date',
        'planned_completion_date',
        'released_by_user_id',
        'released_at',
        'completed_at',
        'production_notes',
        'hold_reason',
        'cancellation_reason',
    ];

    protected $casts = [
        'is_custom_design' => 'boolean',
        'has_storage' => 'boolean',
        'requested_width_cm' => 'decimal:2',
        'requested_length_cm' => 'decimal:2',
        'reference_width_cm' => 'decimal:2',
        'reference_length_cm' => 'decimal:2',
        'ordered_quantity' => 'integer',
        'released_quantity' => 'integer',
        'completed_quantity' => 'integer',
        'planned_start_date' => 'date',
        'planned_completion_date' => 'date',
        'released_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function customerOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderLine::class);
    }

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    public function productConfiguration(): BelongsTo
    {
        return $this->belongsTo(ProductConfiguration::class);
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(ManufacturingRecipeVersion::class, 'manufacturing_recipe_version_id');
    }

    public function customerProductAlias(): BelongsTo
    {
        return $this->belongsTo(CustomerProductAlias::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(ProductionRouting::class, 'production_routing_id');
    }

    public function fabricMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'fabric_material_id');
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class, 'fabric_color_id');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionOrderOperation::class)->orderBy('sequence_number');
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(ProductionMaterialRequirement::class);
    }

    public function materialRequests(): HasMany
    {
        return $this->hasMany(ProductionMaterialRequest::class);
    }

    public function materialIssues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function materialReturns(): HasMany
    {
        return $this->hasMany(MaterialReturn::class);
    }

    public function qualityIncidents(): HasMany
    {
        return $this->hasMany(QualityIncident::class);
    }

    public function wasteRecords(): HasMany
    {
        return $this->hasMany(ProductionWasteRecord::class);
    }

    public function reworkActions(): HasMany
    {
        return $this->hasMany(ProductionReworkAction::class);
    }

    public function finishedGoodsReceipts(): HasMany
    {
        return $this->hasMany(FinishedGoodsReceipt::class);
    }

    public function finishedGoodsMovements(): HasMany
    {
        return $this->hasMany(FinishedGoodsMovement::class);
    }

    public function getHasCustomerOrderChangedAttribute(): bool
    {
        return $this->customerOrder && $this->customerOrder->status === 'PENDING_PRODUCTION_REVIEW';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'DRAFT' => 'bg-secondary',
            'READY_FOR_RELEASE' => 'bg-info text-dark',
            'RELEASED' => 'bg-primary',
            'IN_PROGRESS' => 'bg-warning text-dark',
            'PARTIALLY_COMPLETED' => 'bg-info text-dark',
            'COMPLETED' => 'bg-success',
            'ON_HOLD' => 'bg-dark text-white',
            'CANCELLED' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'DRAFT' => 'مسودة',
            'READY_FOR_RELEASE' => 'جاهز للإطلاق',
            'RELEASED' => 'تم الإطلاق',
            'IN_PROGRESS' => 'قيد التصنيع',
            'PARTIALLY_COMPLETED' => 'مكتمل جزئياً',
            'COMPLETED' => 'مكتمل بالكامل',
            'ON_HOLD' => 'معلق',
            'CANCELLED' => 'ملغى',
            default => $this->status,
        };
    }
}
