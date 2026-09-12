<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionWasteReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function wasteRecords()
    {
        return $this->hasMany(ProductionWasteRecord::class, 'waste_reason_id');
    }
}
