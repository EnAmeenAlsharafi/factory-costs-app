<?php

namespace App\Models;

use Database\Factories\SupplierFabricCatalogColorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierFabricCatalogColor extends Model
{
    /** @use HasFactory<SupplierFabricCatalogColorFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_fabric_catalog_id',
        'fabric_color_id',
        'supplier_color_code',
        'supplier_color_name',
        'is_available',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(SupplierFabricCatalog::class, 'supplier_fabric_catalog_id');
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class);
    }
}
