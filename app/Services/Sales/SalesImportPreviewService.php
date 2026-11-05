<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Material;
use App\Models\SalesImportBatch;
use App\Models\SalesImportRow;
use App\Services\InventoryService;

/**
 * Read-only preview of a staged import batch (spec §6.3 step 2). Never
 * writes anything: re-evaluates matching/stock/classification fresh on
 * every call, so fixing a mapping and reopening the preview reflects it
 * immediately without a new upload.
 */
class SalesImportPreviewService
{
    public function __construct(
        protected SalesItemMatchingService $matcher,
        protected SalesOrderClassifier $classifier,
        protected InventoryService $inventory,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function preview(SalesImportBatch $batch): array
    {
        return $batch->rows->map(fn (SalesImportRow $row) => $this->previewRow($batch, $row))->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewRow(SalesImportBatch $batch, SalesImportRow $row): array
    {
        $data = $row->raw_data;
        $lines = $data['lines'] ?? [];

        $resolvedLines = [];
        $targetTypes = [];

        foreach ($lines as $line) {
            $resolution = $this->matcher->resolveForImport($batch->salesChannel, $line['sku'] ?? null, $line['product_name'] ?? null);
            $targetTypes[] = $resolution['target_type'];

            $resolvedLine = [
                'product_name' => $line['product_name'] ?? null,
                'sku' => $line['sku'] ?? null,
                'quantity' => $line['quantity'] ?? null,
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'target_type' => $resolution['target_type'],
                'mapping' => $resolution['mapping'],
                'ready_stock_availability' => null,
                'bundle_components' => null,
            ];

            if ($resolution['target_type'] === 'READY_STOCK' && $resolution['mapping']?->material_id) {
                $material = Material::find($resolution['mapping']->material_id);
                if ($material) {
                    $resolvedLine['product_name'] = ($line['product_name'] ?? null) ?: ($material->sales_display_name ?: $material->name_ar);
                    $resolvedLine['ready_stock_availability'] = $this->inventory->getReadyProductAvailability($material);
                }
            }

            if ($resolution['target_type'] === 'BUNDLE' && $resolution['mapping']?->bundleDefinition) {
                $resolvedLine['bundle_components'] = $resolution['mapping']->bundleDefinition->components->map(fn ($c) => [
                    'component_type' => $c->component_type,
                    'quantity' => (float) $c->quantity,
                    'product_model_id' => $c->product_model_id,
                    'material_id' => $c->material_id,
                ])->all();
            }

            $resolvedLines[] = $resolvedLine;
        }

        $classification = $this->classifier->classify($data, $targetTypes);

        $mobile = $data['customer_mobile'] ?? null;
        $customer = $mobile ? Customer::where('mobile', $mobile)->first() : null;

        return [
            'row_id' => $row->id,
            'external_order_reference' => $row->external_order_reference,
            'decision' => $classification['decision'],
            'skip_reason' => $classification['skip_reason'],
            'warnings' => $classification['warnings'],
            'customer' => [
                'matched' => $customer !== null,
                'existing_customer_id' => $customer?->id,
                'name' => $data['customer_name'] ?? null,
                'mobile' => $mobile,
            ],
            'payment' => [
                'method' => $data['payment_method'] ?? null,
                'status' => $data['payment_status'] ?? null,
                'reference' => $data['payment_reference'] ?? null,
            ],
            'lines' => $resolvedLines,
        ];
    }
}
