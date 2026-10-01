<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FabricColor extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'color_code',
        'supplier_color_code',
        'color_name_ar',
        'color_name_en',
        'hex_code',
        'pattern',
        'is_available',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function supplierCatalogColors(): HasMany
    {
        return $this->hasMany(SupplierFabricCatalogColor::class, 'fabric_color_id');
    }

    public function supplierCatalogColorFor(?int $supplierId = null): ?SupplierFabricCatalogColor
    {
        $query = $this->supplierCatalogColors()->with('catalog');
        if ($supplierId) {
            $query->whereHas('catalog', fn ($q) => $q->where('supplier_id', $supplierId));
        }

        return $query->first();
    }
}
