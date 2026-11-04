<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch_id',
        'external_order_reference',
        'raw_data',
        'outcome',
        'message',
        'created_customer_order_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(SalesImportBatch::class, 'import_batch_id');
    }

    public function createdCustomerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'created_customer_order_id');
    }
}
