<?php

namespace Tests\Feature\Reports;

use App\Domain\Reports\InventoryAnalyticsService;
use App\Domain\Reports\ProcurementAnalyticsService;
use App\Domain\Reports\ReportPeriod;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Services\InventoryService;

class InventoryProcurementReportTest extends ReportingTestCase
{
    public function test_operational_stock_value_is_remaining_quantity_times_lot_cost(): void
    {
        $this->lot(10, 20);
        $this->lot(5, 30);

        $inventory = app(InventoryAnalyticsService::class);
        $dataset = $inventory->stock(['search' => $this->material->code]);
        $row = $dataset->mapRows($dataset->query->get())->first();

        $this->assertSame(350.0, $inventory->totalStockValue());
        $this->assertSame(350.0, $row['stock_value']);
        $this->assertSame(15.0, $row['stock_qty']);
    }

    public function test_stock_report_excludes_open_purchase_quantity_from_physical_stock(): void
    {
        $this->lot(10, 20);
        $this->purchaseOrder(Supplier::factory()->create(), 'SENT', 40, 20, now()->addDays(3)->toDateString());

        $dataset = app(InventoryAnalyticsService::class)->stock(['search' => $this->material->code]);
        $row = $dataset->mapRows($dataset->query->get())->first();

        $this->assertSame(10.0, $row['stock_qty']);
        $this->assertSame(40.0, $row['incoming_qty']);
        $this->assertSame(50.0, $row['available_plus_incoming']);
    }

    public function test_purchase_price_variance_compares_actual_receipt_cost_with_po_price(): void
    {
        $supplier = Supplier::factory()->create();
        $po = $this->purchaseOrder($supplier, 'RECEIVED', 100, 20, now()->toDateString());
        $this->receipt($supplier, $po, 100, 22, now()->toDateString());

        $procurement = app(ProcurementAnalyticsService::class);
        $dataset = $procurement->priceVariance(ReportPeriod::preset('this_month'));
        $row = $dataset->mapRows($dataset->query->get())->first();

        $this->assertSame(20.0, $row['po_price_per_base']);
        $this->assertSame(22.0, $row['actual_price_per_base']);
        $this->assertSame(2.0, $row['variance_per_base_unit']);
        $this->assertSame(200.0, $row['variance_total']);
        $this->assertSame('20.0000', (string) $po->lines()->first()->unit_price);
        $this->assertSame(200.0, $procurement->summary(ReportPeriod::preset('this_month'))['price_variance']);
    }

    public function test_supplier_metrics_count_on_time_late_and_partial_orders(): void
    {
        $supplier = Supplier::factory()->create();
        $onTime = $this->purchaseOrder($supplier, 'RECEIVED', 10, 5, now()->subDays(2)->toDateString(), now()->subDays(10)->toDateString());
        $this->receipt($supplier, $onTime, 10, 5, now()->subDays(3)->toDateString());
        $lateReceived = $this->purchaseOrder($supplier, 'RECEIVED', 10, 5, now()->subDays(8)->toDateString(), now()->subDays(12)->toDateString());
        $this->receipt($supplier, $lateReceived, 10, 5, now()->subDays(4)->toDateString());
        $partial = $this->purchaseOrder($supplier, 'PARTIALLY_RECEIVED', 10, 5, now()->addDays(5)->toDateString(), now()->subDays(4)->toDateString());
        $this->receipt($supplier, $partial, 4, 5, now()->subDays(1)->toDateString());

        $row = app(ProcurementAnalyticsService::class)->suppliers(ReportPeriod::preset('this_year'))->firstWhere('supplier', $supplier->name);

        $this->assertSame(3, $row['po_count']);
        $this->assertSame(2, $row['completed_pos']);
        $this->assertSame(50.0, $row['on_time_pct']);
        $this->assertSame(1, $row['late_pos']);
        $this->assertSame(1, $row['partial_pos']);
        $this->assertSame(120.0, $row['received_value']);
    }

    public function test_usable_return_created_from_issue_lines_is_linked_to_the_production_order(): void
    {
        [, $line] = $this->order(1000);
        $po = $this->productionOrder($line, 1);
        $lot = $this->lot(20, 10);
        $issue = $this->postedIssue($po, [[$lot, 10]]);

        $return = app(InventoryService::class)->createReturn([
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'lines' => [['original_issue_line_id' => $issue->lines()->first()->id, 'inventory_lot_id' => $lot->id, 'material_id' => $lot->material_id, 'returned_quantity' => 3]],
        ], $this->admin->id);

        $this->assertSame($po->id, $return->fresh()->production_order_id);
    }

    public function test_return_without_issue_traceability_stays_unlinked(): void
    {
        $lot = $this->lot(20, 10);

        $return = app(InventoryService::class)->createReturn([
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'lines' => [['inventory_lot_id' => $lot->id, 'material_id' => $lot->material_id, 'returned_quantity' => 3]],
        ], $this->admin->id);

        $this->assertNull($return->fresh()->production_order_id);
    }

    private function purchaseOrder(Supplier $supplier, string $status, float $quantity, float $price, ?string $expected, ?string $orderDate = null): PurchaseOrder
    {
        $this->sequence++;
        $po = PurchaseOrder::create([
            'purchase_order_number' => 'PO-RPT-'.$this->sequence,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => $orderDate ?? now()->toDateString(),
            'expected_delivery_date' => $expected,
            'status' => $status,
            'total_amount' => $quantity * $price,
            'created_by_user_id' => $this->admin->id,
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'material_id' => $this->material->id,
            'ordered_quantity' => $quantity,
            'purchase_unit_id' => $this->material->base_unit_id,
            'conversion_factor' => 1,
            'ordered_base_quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $quantity * $price,
            'received_base_quantity' => 0,
        ]);

        return $po;
    }

    private function receipt(Supplier $supplier, PurchaseOrder $po, float $quantity, float $unitCost, string $date): void
    {
        $this->sequence++;
        $receipt = MaterialReceipt::create([
            'receipt_number' => 'REC-RPT-'.$this->sequence,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => $date,
            'status' => 'POSTED',
            'created_by_user_id' => $this->admin->id,
            'posted_at' => now(),
        ]);
        $poLine = $po->lines()->first();
        MaterialReceiptLine::create([
            'material_receipt_id' => $receipt->id,
            'material_id' => $this->material->id,
            'purchase_order_line_id' => $poLine->id,
            'quantity_received' => $quantity,
            'purchase_unit_id' => $this->material->base_unit_id,
            'conversion_factor' => 1,
            'base_quantity' => $quantity,
            'base_unit_id' => $this->material->base_unit_id,
            'unit_cost_purchase' => $unitCost,
            'unit_cost_base' => $unitCost,
            'total_cost' => $quantity * $unitCost,
        ]);
        $poLine->increment('received_base_quantity', $quantity);
    }
}
