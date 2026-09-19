<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_code',
        'material_id',
        'fabric_color_id',
        'fabric_color_code',
        'supplier_id',
        'warehouse_id',
        'receipt_line_id',
        'received_date',
        'original_quantity',
        'remaining_quantity',
        'base_unit_id',
        'unit_cost',
        'quality_grade',
        'supplier_lot_reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'original_quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fabricColor(): BelongsTo
    {
        return $this->belongsTo(FabricColor::class, 'fabric_color_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(MaterialReceiptLine::class, 'receipt_line_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function issueLines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE')->where('remaining_quantity', '>', 0);
    }

    public function getLotValuationAttribute(): float
    {
        return (float) ($this->remaining_quantity * $this->unit_cost);
    }

    public function getLotNumberAttribute(): string
    {
        return $this->lot_code ?? '';
    }

    public function getInitialQuantityAttribute(): float
    {
        return (float) ($this->original_quantity ?? 0);
    }

    public function getReceiptAttribute(): ?MaterialReceipt
    {
        return $this->receiptLine?->materialReceipt;
    }
}
