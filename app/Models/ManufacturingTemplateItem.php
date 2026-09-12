<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturing_template_id',
        'item_type',
        'material_id',
        'semi_finished_component_id',
        'quantity',
        'unit_id',
        'waste_percentage',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'waste_percentage' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Parent template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ManufacturingTemplate::class, 'manufacturing_template_id');
    }

    /**
     * Material.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Semi-Finished Component.
     */
    public function semiFinishedComponent(): BelongsTo
    {
        return $this->belongsTo(SemiFinishedComponent::class);
    }

    /**
     * Unit of Measure.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * Calculated planned quantity = quantity * (1 + waste_percentage / 100).
     */
    public function getPlannedQuantityAttribute(): float
    {
        $wasteMultiplier = 1 + (((float) $this->waste_percentage) / 100);

        return (float) $this->quantity * $wasteMultiplier;
    }
}
