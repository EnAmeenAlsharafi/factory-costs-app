<?php

namespace App\Services\Sales;

use App\Models\SalesBundleDefinition;

/**
 * Expands a bundle definition into the child order-line arrays for
 * CustomerOrderService::syncLines() — a BUNDLE parent line carries the
 * price the customer paid; every component child is priced at zero.
 */
class SalesBundleExpansionService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function expand(SalesBundleDefinition $bundle, float $orderedQty): array
    {
        $children = [];

        foreach ($bundle->components as $component) {
            $qty = (float) $component->quantity * $orderedQty;

            $line = [
                'line_type' => $component->component_type,
                'quantity' => $qty,
                'unit_price' => 0,
                'discount_amount' => 0,
                'is_incomplete' => $component->component_type === 'MANUFACTURED',
            ];

            if ($component->component_type === 'MANUFACTURED') {
                $line['product_model_id'] = $component->product_model_id;
                $line['product_configuration_id'] = $component->product_configuration_id;

                if ($component->product_configuration_id && $component->productConfiguration) {
                    // Size is prefilled from the fixed configuration, but fabric/color
                    // still need Customer Service, so the line remains incomplete.
                    $line['requested_width_cm'] = $component->productConfiguration->width_cm;
                    $line['requested_length_cm'] = $component->productConfiguration->length_cm;
                }
            } else {
                $line['material_id'] = $component->material_id;
            }

            $children[] = $line;
        }

        return $children;
    }
}
