<?php

namespace App\Models;

use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'material_category_id',
        'name_ar',
        'name_en',
        'base_unit_id',
        'purchase_unit_id',
        'min_stock_level',
        'reorder_point',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'min_stock_level' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Generate the next stable, concurrency-safe sequential material code (e.g. MAT-000001).
     */
    public static function generateNextCode(): string
    {
        return DocumentNumberService::generateMaterialCode();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_unit_id');
    }

    public function woodSpec(): HasOne
    {
        return $this->hasOne(WoodMaterialSpec::class);
    }

    public function foamSpec(): HasOne
    {
        return $this->hasOne(FoamMaterialSpec::class);
    }

    public function fabricSpec(): HasOne
    {
        return $this->hasOne(FabricMaterialSpec::class);
    }

    public function fabricColors(): HasMany
    {
        return $this->hasMany(FabricColor::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'material_supplier')
            ->withPivot(['supplier_item_code', 'lead_time_days', 'minimum_order_qty', 'is_preferred', 'notes'])
            ->withTimestamps();
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(MaterialUnitConversion::class);
    }

    public function conversions(): HasMany
    {
        return $this->unitConversions();
    }

    public function getNameAttribute(): string
    {
        return $this->name_ar;
    }

    public function getAverageUnitCostAttribute(): float
    {
        $activeLots = InventoryLot::where('material_id', $this->id)
            ->where('remaining_quantity', '>', 0)
            ->get();

        if ($activeLots->isEmpty()) {
            return 0.0;
        }

        $totalValuation = $activeLots->sum(fn ($lot) => $lot->remaining_quantity * $lot->unit_cost);
        $totalQty = $activeLots->sum('remaining_quantity');

        return $totalQty > 0 ? round($totalValuation / $totalQty, 6) : 0.0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getSpecificationSummaryAttribute(): string
    {
        if ($this->category?->isWood() && $this->woodSpec) {
            return "{$this->woodSpec->wood_type} • سماكة {$this->woodSpec->thickness_mm}ملم • {$this->woodSpec->width_cm}×{$this->woodSpec->length_cm} سم";
        }

        if ($this->category?->isFoam() && $this->foamSpec) {
            $dims = ($this->foamSpec->width_cm && $this->foamSpec->length_cm)
                ? "{$this->foamSpec->width_cm}×{$this->foamSpec->length_cm} سم"
                : ($this->foamSpec->block_dimensions ?? '');
            $density = $this->foamSpec->density_kg_m3 ? "كثافة {$this->foamSpec->density_kg_m3}" : '';

            return "{$this->foamSpec->foam_type} • {$density} • سماكة {$this->foamSpec->thickness_mm}ملم ".($dims ? "• {$dims}" : '');
        }

        if ($this->category?->isFabric() && $this->fabricSpec) {
            return "{$this->fabricSpec->fabric_type} • عرض {$this->fabricSpec->width_cm}سم";
        }

        return $this->notes ?? '-';
    }
}
