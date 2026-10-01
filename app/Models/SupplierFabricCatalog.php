<?php

namespace App\Models;

use Database\Factories\SupplierFabricCatalogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierFabricCatalog extends Model
{
    /** @use HasFactory<SupplierFabricCatalogFactory> */
    use HasFactory;

    protected $fillable = [
        'material_id',
        'supplier_id',
        'catalog_number',
        'catalog_name',
        'supplier_material_code',
        'image_path',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(SupplierFabricCatalogColor::class);
    }
}
