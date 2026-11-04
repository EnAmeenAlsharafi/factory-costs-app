<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_hash',
        'sales_channel_id',
        'uploaded_by_user_id',
        'created_count',
        'skipped_count',
        'failed_count',
        'status',
    ];

    protected $casts = [
        'created_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SalesImportRow::class, 'import_batch_id');
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class, 'import_batch_id');
    }
}
