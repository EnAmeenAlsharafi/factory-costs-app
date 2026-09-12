<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'configuration_code',
        'product_model_id',
        'standard_bed_size_id',
        'width_cm',
        'length_cm',
        'has_storage',
        'configuration_name',
        'is_standard',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'width_cm' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'has_storage' => 'boolean',
        'is_standard' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_model_id');
    }

    public function standardBedSize(): BelongsTo
    {
        return $this->belongsTo(StandardBedSize::class, 'standard_bed_size_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $dimensions = number_format($this->width_cm, 0).' × '.number_format($this->length_cm, 0).' سم';
        $storageLabel = $this->has_storage ? 'تخزين' : 'بدون تخزين';

        if ($this->configuration_name) {
            return "{$this->configuration_name} ({$dimensions} - {$storageLabel})";
        }

        return "{$dimensions} - {$storageLabel}";
    }
}
