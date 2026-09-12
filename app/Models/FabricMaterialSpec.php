<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricMaterialSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'fabric_type',
        'pattern_type',
        'width_cm',
        'weight_gsm',
        'composition',
        'martindale_rub_count',
    ];

    protected $casts = [
        'width_cm' => 'decimal:2',
        'weight_gsm' => 'decimal:2',
        'martindale_rub_count' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
