<?php

namespace App\Services;

use App\Models\FabricCompany;
use App\Models\FabricPrice;
use App\Models\FabricType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ReferenceDataService
{
    /**
     * Get all active products with their cost standards in exact order.
     */
    public function getOrderedProducts(): Collection
    {
        return Product::with('costStandard')
            ->where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();
    }

    /**
     * Get all active fabric companies.
     */
    public function getFabricCompanies(): Collection
    {
        return FabricCompany::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Get all active fabric types.
     */
    public function getFabricTypes(): Collection
    {
        return FabricType::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Get fabric price matrix: array keyed by "company_id-type_id" => price_per_meter.
     */
    public function getFabricPriceMatrix(): array
    {
        $prices = FabricPrice::all();
        $matrix = [];
        foreach ($prices as $price) {
            $key = "{$price->fabric_company_id}_{$price->fabric_type_id}";
            $matrix[$key] = (float) $price->price_per_meter;
        }

        return $matrix;
    }

    /**
     * Find fabric price per meter by company ID and type ID.
     */
    public function getFabricPrice(int $companyId, int $typeId): float
    {
        $price = FabricPrice::where('fabric_company_id', $companyId)
            ->where('fabric_type_id', $typeId)
            ->first();

        return $price ? (float) $price->price_per_meter : 0.0;
    }
}
