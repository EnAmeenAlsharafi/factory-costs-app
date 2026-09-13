<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRfqSupplier extends Model
{
    use HasFactory;

    protected $table = 'purchase_rfq_suppliers';

    protected $fillable = [
        'purchase_rfq_id',
        'supplier_id',
        'status',
        'notes',
    ];

    public function purchaseRfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
