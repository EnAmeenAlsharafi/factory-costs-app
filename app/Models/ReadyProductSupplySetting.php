<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a supplier-made ready product (one mattress size) is supplied: its supplier, the level that
 * triggers a production request, and the supplier's minimum production batch.
 */
class ReadyProductSupplySetting extends Model
{
    protected $fillable = [
        'material_id',
        'supplier_id',
        'reorder_level',
        'production_batch_size',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'integer',
            'production_batch_size' => 'integer',
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
}
