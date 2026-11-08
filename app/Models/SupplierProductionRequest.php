<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request asking a supplier to produce a batch for us; once produced, the batch is held at the supplier.
 */
class SupplierProductionRequest extends Model
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const STATUS_LABELS = [
        'REQUESTED' => ['بانتظار الإنتاج', 'warning'],
        'PRODUCED' => ['تم الإنتاج', 'success'],
        'CANCELLED' => ['ملغى', 'secondary'],
    ];

    protected $fillable = [
        'request_number',
        'supplier_id',
        'status',
        'request_date',
        'notes',
        'created_by_user_id',
        'produced_at',
        'produced_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'produced_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierProductionRequestLine::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
