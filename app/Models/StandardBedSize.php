<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardBedSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'width_cm',
        'length_cm',
        'name_ar',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'width_cm' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function configurations(): HasMany
    {
        return $this->hasMany(ProductConfiguration::class, 'standard_bed_size_id');
    }

    public function getFormattedDimensionsAttribute(): string
    {
        return number_format($this->width_cm, 0).' × '.number_format($this->length_cm, 0).' سم';
    }
}
