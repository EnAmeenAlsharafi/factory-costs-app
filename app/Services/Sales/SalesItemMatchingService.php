<?php

namespace App\Services\Sales;

use App\Models\SalesChannel;
use App\Models\SalesItemMapping;

/**
 * Resolves a Salla order line (SKU and/or product name) to a mapped target
 * — manufactured model/configuration, ready-stock material, bundle
 * definition, or an explicit IGNORE — via sales_item_mappings. SKU is tried
 * first, then the normalized product name.
 */
class SalesItemMatchingService
{
    /**
     * @return array{target_type: string|null, mapping: SalesItemMapping|null}
     */
    public function resolve(SalesChannel $salesChannel, ?string $sku, ?string $name): array
    {
        $normalizedSku = SallaValueNormalizer::normalizeMatchValue(SallaValueNormalizer::nullIfPlaceholder($sku));

        if ($normalizedSku) {
            $mapping = SalesItemMapping::active()
                ->where('sales_channel_id', $salesChannel->id)
                ->where('match_type', 'SKU')
                ->where('match_value', $normalizedSku)
                ->first();

            if ($mapping) {
                return ['target_type' => $mapping->target_type, 'mapping' => $mapping];
            }
        }

        $normalizedName = SallaValueNormalizer::normalizeMatchValue($name);

        if ($normalizedName) {
            $mapping = SalesItemMapping::active()
                ->where('sales_channel_id', $salesChannel->id)
                ->where('match_type', 'NAME')
                ->where('match_value', $normalizedName)
                ->first();

            if ($mapping) {
                return ['target_type' => $mapping->target_type, 'mapping' => $mapping];
            }
        }

        return ['target_type' => null, 'mapping' => null];
    }
}
