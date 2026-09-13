<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRfq extends Model
{
    use HasFactory;

    protected $table = 'purchase_rfqs';

    protected $fillable = [
        'rfq_number',
        'purchase_request_id',
        'issue_date',
        'response_due_date',
        'status',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'response_due_date' => 'date',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function rfqSuppliers(): HasMany
    {
        return $this->hasMany(PurchaseRfqSupplier::class, 'purchase_rfq_id');
    }

    public function supplierQuotations(): HasMany
    {
        return $this->hasMany(SupplierQuotation::class, 'purchase_rfq_id');
    }
}
