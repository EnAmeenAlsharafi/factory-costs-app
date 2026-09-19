<?php

namespace App\Models;

use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    use HasFactory;

    /**
     * Accessor for name_ar to provide compatibility with views expecting name_ar.
     */
    protected function nameAr(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name,
        );
    }

    protected $fillable = [
        'supplier_code',
        'name',
        'commercial_name',
        'contact_person',
        'mobile',
        'phone',
        'email',
        'tax_number',
        'commercial_registration',
        'city',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Generate the next stable sequential supplier code (e.g. SUP-000001).
     */
    public static function generateNextCode(): string
    {
        return DocumentNumberService::generateSupplierCode();
    }

    /**
     * Scope to active suppliers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query filters for search and status.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('supplier_code', 'like', "%{$search}%")
                        ->orWhere('commercial_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('is_active', $filters['status'] === 'active' || $filters['status'] === '1');
            });
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'material_supplier')
            ->withPivot(['supplier_item_code', 'lead_time_days', 'minimum_order_qty', 'is_preferred', 'notes'])
            ->withTimestamps();
    }
}
