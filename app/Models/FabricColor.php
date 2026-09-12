<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricColor extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'color_code',
        'color_name_ar',
        'color_name_en',
        'hex_code',
        'pattern',
        'is_active',
        'notes',
    ];

    protected $casts = [
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
}
