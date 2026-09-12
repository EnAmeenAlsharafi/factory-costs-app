<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoamMaterialSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'foam_type',
        'density_kg_m3',
        'hardness_rating',
        'thickness_mm',
        'width_cm',
        'length_cm',
        'block_dimensions',
    ];

    protected $casts = [
        'density_kg_m3' => 'decimal:2',
        'thickness_mm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'length_cm' => 'decimal:2',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
