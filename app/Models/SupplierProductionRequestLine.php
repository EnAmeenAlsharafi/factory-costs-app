<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductionRequestLine extends Model
{
    protected $fillable = [
        'supplier_production_request_id',
        'material_id',
        'quantity',
        'produced_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'produced_quantity' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplierProductionRequest::class, 'supplier_production_request_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
