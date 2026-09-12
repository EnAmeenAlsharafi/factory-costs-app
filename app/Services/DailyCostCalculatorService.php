<?php

namespace App\Services;

use App\Models\FabricPrice;
use App\Models\Product;

class DailyCostCalculatorService
{
    /**
     * Calculate costs for all daily report items and aggregate daily totals.
     *
     * @param  array  $submittedItems  Array of [product_id, quantity, storage_type, fabric_company_id, fabric_type_id]
     * @return array [ 'items' => array, 'totals' => array ]
     */
    public function calculate(array $submittedItems): array
    {
        // Preload products with standards
        $products = Product::with('costStandard')->get()->keyBy('id');

        // Preload all fabric prices for fast O(1) lookup
        $fabricPrices = FabricPrice::all()
            ->keyBy(fn ($fp) => "{$fp->fabric_company_id}_{$fp->fabric_type_id}");

        $calculatedItems = [];
        $totalQuantity = 0;
        $totalTransport = '0.00';
        $totalWood = '0.00';
        $totalFoam = '0.00';
        $totalFabric = '0.00';
        $totalPaint = '0.00';
        $totalNails = '0.00';
        $totalHinges = '0.00';
        $totalPackaging = '0.00';
        $totalCarpentryWages = '0.00';
        $totalUpholsteryWages = '0.00';
        $totalPackagingWages = '0.00';
        $totalAdministrativeWages = '0.00';
        $totalAdvertising = '0.00';
        $totalShipping = '0.00';
        $totalMiscellaneous = '0.00';
        $totalProfitMargin = '0.00';
        $totalCost = '0.00';

        foreach ($submittedItems as $input) {
            $productId = (int) ($input['product_id'] ?? 0);
            $quantity = max(0, (int) ($input['quantity'] ?? 0));
            $storageType = ($input['storage_type'] ?? 'بدون تخزين') === 'بتخزين' ? 'بتخزين' : 'بدون تخزين';
            $fabricCompanyId = ! empty($input['fabric_company_id']) ? (int) $input['fabric_company_id'] : null;
            $fabricTypeId = ! empty($input['fabric_type_id']) ? (int) $input['fabric_type_id'] : null;

            /** @var Product|null $product */
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $standard = $product->costStandard;

            // Unit costs
            $unitTransport = $standard ? (float) $standard->raw_material_transport : 0.0;
            $unitWood = 0.0;
            if ($standard) {
                $unitWood = ($storageType === 'بتخزين')
                    ? (float) $standard->wood_with_storage
                    : (float) $standard->wood_without_storage;
            }

            $unitFoam = $standard ? (float) $standard->foam : 0.0;
            $unitFabricMeters = $standard ? (float) $standard->fabric_meters : 0.0;

            // Fabric price per meter
            $unitFabricPricePerMeter = 0.0;
            if ($fabricCompanyId && $fabricTypeId) {
                $priceKey = "{$fabricCompanyId}_{$fabricTypeId}";
                if ($fabricPrices->has($priceKey)) {
                    $unitFabricPricePerMeter = (float) $fabricPrices->get($priceKey)->price_per_meter;
                }
            }

            // In Excel: Unit Fabric Cost = meters * price_per_meter
            $unitFabricCost = round($unitFabricMeters * $unitFabricPricePerMeter, 4);

            $unitPaint = $standard ? (float) $standard->paint : 0.0;
            $unitNails = $standard ? (float) $standard->nails : 0.0;
            $unitHinges = $standard ? (float) $standard->hinges : 0.0;
            $unitPackaging = $standard ? (float) $standard->packaging : 0.0;
            $unitCarpentryWages = $standard ? (float) $standard->carpentry_wages : 0.0;
            $unitUpholsteryWages = $standard ? (float) $standard->upholstery_wages : 0.0;
            $unitPackagingWages = $standard ? (float) $standard->packaging_wages : 0.0;
            $unitAdministrativeWages = $standard ? (float) $standard->administrative_wages : 0.0;
            $unitAdvertising = $standard ? (float) $standard->advertising : 0.0;
            $unitShipping = $standard ? (float) $standard->shipping : 0.0;
            $unitMiscellaneous = $standard ? (float) $standard->miscellaneous : 0.0;
            $unitProfitMargin = $standard ? (float) $standard->profit_margin : 0.0;

            $unitTotalCost = $unitTransport + $unitWood + $unitFoam + $unitFabricCost +
                $unitPaint + $unitNails + $unitHinges + $unitPackaging +
                $unitCarpentryWages + $unitUpholsteryWages + $unitPackagingWages +
                $unitAdministrativeWages + $unitAdvertising + $unitShipping +
                $unitMiscellaneous + $unitProfitMargin;

            // Line totals (if quantity == 0, line totals are 0.00)
            if ($quantity > 0) {
                $lineTransport = round($unitTransport * $quantity, 2);
                $lineWood = round($unitWood * $quantity, 2);
                $lineFoam = round($unitFoam * $quantity, 2);
                $lineFabric = round($unitFabricCost * $quantity, 2);
                $linePaint = round($unitPaint * $quantity, 2);
                $lineNails = round($unitNails * $quantity, 2);
                $lineHinges = round($unitHinges * $quantity, 2);
                $linePackaging = round($unitPackaging * $quantity, 2);
                $lineCarpentryWages = round($unitCarpentryWages * $quantity, 2);
                $lineUpholsteryWages = round($unitUpholsteryWages * $quantity, 2);
                $linePackagingWages = round($unitPackagingWages * $quantity, 2);
                $lineAdministrativeWages = round($unitAdministrativeWages * $quantity, 2);
                $lineAdvertising = round($unitAdvertising * $quantity, 2);
                $lineShipping = round($unitShipping * $quantity, 2);
                $lineMiscellaneous = round($unitMiscellaneous * $quantity, 2);
                $lineProfitMargin = round($unitProfitMargin * $quantity, 2);

                $lineTotalCost = $lineTransport + $lineWood + $lineFoam + $lineFabric +
                    $linePaint + $lineNails + $lineHinges + $linePackaging +
                    $lineCarpentryWages + $lineUpholsteryWages + $linePackagingWages +
                    $lineAdministrativeWages + $lineAdvertising + $lineShipping +
                    $lineMiscellaneous + $lineProfitMargin;
            } else {
                $lineTransport = 0.00;
                $lineWood = 0.00;
                $lineFoam = 0.00;
                $lineFabric = 0.00;
                $linePaint = 0.00;
                $lineNails = 0.00;
                $lineHinges = 0.00;
                $linePackaging = 0.00;
                $lineCarpentryWages = 0.00;
                $lineUpholsteryWages = 0.00;
                $linePackagingWages = 0.00;
                $lineAdministrativeWages = 0.00;
                $lineAdvertising = 0.00;
                $lineShipping = 0.00;
                $lineMiscellaneous = 0.00;
                $lineProfitMargin = 0.00;
                $lineTotalCost = 0.00;
            }

            // Aggregate daily totals
            $totalQuantity += $quantity;
            $totalTransport = bcadd((string) $totalTransport, (string) $lineTransport, 2);
            $totalWood = bcadd((string) $totalWood, (string) $lineWood, 2);
            $totalFoam = bcadd((string) $totalFoam, (string) $lineFoam, 2);
            $totalFabric = bcadd((string) $totalFabric, (string) $lineFabric, 2);
            $totalPaint = bcadd((string) $totalPaint, (string) $linePaint, 2);
            $totalNails = bcadd((string) $totalNails, (string) $lineNails, 2);
            $totalHinges = bcadd((string) $totalHinges, (string) $lineHinges, 2);
            $totalPackaging = bcadd((string) $totalPackaging, (string) $linePackaging, 2);
            $totalCarpentryWages = bcadd((string) $totalCarpentryWages, (string) $lineCarpentryWages, 2);
            $totalUpholsteryWages = bcadd((string) $totalUpholsteryWages, (string) $lineUpholsteryWages, 2);
            $totalPackagingWages = bcadd((string) $totalPackagingWages, (string) $linePackagingWages, 2);
            $totalAdministrativeWages = bcadd((string) $totalAdministrativeWages, (string) $lineAdministrativeWages, 2);
            $totalAdvertising = bcadd((string) $totalAdvertising, (string) $lineAdvertising, 2);
            $totalShipping = bcadd((string) $totalShipping, (string) $lineShipping, 2);
            $totalMiscellaneous = bcadd((string) $totalMiscellaneous, (string) $lineMiscellaneous, 2);
            $totalProfitMargin = bcadd((string) $totalProfitMargin, (string) $lineProfitMargin, 2);
            $totalCost = bcadd((string) $totalCost, (string) $lineTotalCost, 2);

            $calculatedItems[] = [
                'product_id' => $productId,
                'product' => $product,
                'quantity' => $quantity,
                'storage_type' => $storageType,
                'fabric_company_id' => $fabricCompanyId,
                'fabric_type_id' => $fabricTypeId,

                // Snapshots
                'unit_raw_material_transport' => $unitTransport,
                'unit_wood_cost' => $unitWood,
                'unit_foam_cost' => $unitFoam,
                'unit_fabric_meters' => $unitFabricMeters,
                'unit_fabric_price_per_meter' => $unitFabricPricePerMeter,
                'unit_fabric_cost' => $unitFabricCost,
                'unit_paint_cost' => $unitPaint,
                'unit_nails_cost' => $unitNails,
                'unit_hinges_cost' => $unitHinges,
                'unit_packaging_cost' => $unitPackaging,
                'unit_carpentry_wages' => $unitCarpentryWages,
                'unit_upholstery_wages' => $unitUpholsteryWages,
                'unit_packaging_wages' => $unitPackagingWages,
                'unit_administrative_wages' => $unitAdministrativeWages,
                'unit_advertising' => $unitAdvertising,
                'unit_shipping' => $unitShipping,
                'unit_miscellaneous' => $unitMiscellaneous,
                'unit_profit_margin' => $unitProfitMargin,
                'unit_total_cost' => $unitTotalCost,

                // Line totals
                'total_transport' => $lineTransport,
                'total_wood' => $lineWood,
                'total_foam' => $lineFoam,
                'total_fabric' => $lineFabric,
                'total_paint' => $linePaint,
                'total_nails' => $lineNails,
                'total_hinges' => $lineHinges,
                'total_packaging' => $linePackaging,
                'total_carpentry_wages' => $lineCarpentryWages,
                'total_upholstery_wages' => $lineUpholsteryWages,
                'total_packaging_wages' => $linePackagingWages,
                'total_administrative_wages' => $lineAdministrativeWages,
                'total_advertising' => $lineAdvertising,
                'total_shipping' => $lineShipping,
                'total_miscellaneous' => $lineMiscellaneous,
                'total_profit_margin' => $lineProfitMargin,
                'line_total_cost' => $lineTotalCost,
            ];
        }

        return [
            'items' => $calculatedItems,
            'totals' => [
                'total_quantity' => $totalQuantity,
                'total_transport' => (float) $totalTransport,
                'total_wood' => (float) $totalWood,
                'total_foam' => (float) $totalFoam,
                'total_fabric' => (float) $totalFabric,
                'total_paint' => (float) $totalPaint,
                'total_nails' => (float) $totalNails,
                'total_hinges' => (float) $totalHinges,
                'total_packaging' => (float) $totalPackaging,
                'total_carpentry_wages' => (float) $totalCarpentryWages,
                'total_upholstery_wages' => (float) $totalUpholsteryWages,
                'total_packaging_wages' => (float) $totalPackagingWages,
                'total_administrative_wages' => (float) $totalAdministrativeWages,
                'total_advertising' => (float) $totalAdvertising,
                'total_shipping' => (float) $totalShipping,
                'total_miscellaneous' => (float) $totalMiscellaneous,
                'total_profit_margin' => (float) $totalProfitMargin,
                'total_cost' => (float) $totalCost,
            ],
        ];
    }
}
