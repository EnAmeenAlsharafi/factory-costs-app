<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoodMaterialSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'wood_type',
        'thickness_mm',
        'width_cm',
        'length_cm',
        'grade',
    ];

    protected $casts = [
        'thickness_mm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'length_cm' => 'decimal:2',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
