<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_number',
        'customer_order_id',
        'customer_id',
        'sales_channel_id',
        'scheduled_delivery_date',
        'scheduled_time_notes',
        'status',
        'assigned_user_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'city_snapshot',
        'district_snapshot',
        'delivery_address_snapshot',
        'location_notes',
        'installation_required',
        'delivery_notes',
        'installation_notes',
        'created_by_user_id',
        'dispatched_by_user_id',
        'dispatched_at',
        'delivered_at',
        'installed_at',
    ];

    protected $casts = [
        'scheduled_delivery_date' => 'date',
        'installation_required' => 'boolean',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'installed_at' => 'datetime',
    ];

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at', 'desc');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinishedGoodsMovement::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    public function isDispatched(): bool
    {
        return in_array($this->status, ['OUT_FOR_DELIVERY', 'DELIVERED', 'INSTALLATION_COMPLETED'], true);
    }

    public function isCompleted(): bool
    {
        if ($this->installation_required) {
            return $this->status === 'INSTALLATION_COMPLETED';
        }

        return in_array($this->status, ['DELIVERED', 'INSTALLATION_COMPLETED'], true);
    }
}
