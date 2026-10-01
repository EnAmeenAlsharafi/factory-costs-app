<?php

namespace Tests\Feature\Reports;

use App\Domain\Reports\ProductionAnalyticsService;
use App\Domain\Reports\ProfitabilityReportingService;
use App\Domain\Reports\ReportPeriod;
use App\Models\CustomerReturn;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\ProductModel;
use App\Models\Supplier;
use App\Services\ProductionCostService;

class OperationalProfitabilityReportTest extends ReportingTestCase
{
    private function orderRow(int $orderId): array
    {
        $dataset = app(ProfitabilityReportingService::class)->orders(ReportPeriod::preset('this_year'), ['include_cancelled' => true]);

        return $dataset->mapRows($dataset->query->get())->firstWhere('id', $orderId);
    }

    public function test_operational_contribution_is_value_minus_actual_material_cost(): void
    {
        [$order, $line] = $this->order(10000);
        $po = $this->productionOrder($line, 1);
        $this->postedIssue($po, [[$this->lot(100, 60), 100]]);

        $row = $this->orderRow($order->id);

        $this->assertSame(10000.0, $row['commercial_value']);
        $this->assertSame(6000.0, $row['actual_cost']);
        $this->assertSame(4000.0, $row['contribution']);
        $this->assertSame(40.0, $row['contribution_pct']);
        $this->assertSame('نهائية تشغيلياً', $row['profitability_status']['label']);
    }

    public function test_actual_cost_uses_lot_costs_net_of_usable_returns_and_matches_stage_ten_service(): void
    {
        [$order, $line] = $this->order(1000);
        $po = $this->productionOrder($line, 1);
        $lotA = $this->lot(10, 20);
        $lotB = $this->lot(5, 25);
        $this->postedIssue($po, [[$lotA, 10], [$lotB, 5]]);
        $this->postedReturn($po, $lotA, 2);

        $expected = (10 * 20 + 5 * 25) - (2 * 20);
        $stageTen = app(ProductionCostService::class)->calculateOrderMaterialCost($po);

        $this->assertSame((float) $expected, $stageTen['actual_net_material_cost']);
        $this->assertSame((float) $expected, $this->orderRow($order->id)['actual_cost']);
    }

    public function test_waste_cost_is_analytical_and_not_added_to_actual_cost(): void
    {
        [$order, $line] = $this->order(5000);
        $po = $this->productionOrder($line, 1);
        $lot = $this->lot(100, 30);
        $this->postedIssue($po, [[$lot, 100]]);
        ProductionWasteRecord::create([
            'waste_number' => 'WST-RPT-1', 'production_order_id' => $po->id, 'material_id' => $lot->material_id,
            'inventory_lot_id' => $lot->id, 'quantity' => 500 / 30, 'unit_id' => $lot->base_unit_id, 'unit_cost' => 30,
            'total_cost' => 500, 'waste_reason_id' => ProductionWasteReason::orderBy('id')->value('id'),
            'recorded_by_user_id' => $this->admin->id, 'occurred_at' => now(),
        ]);

        $row = $this->orderRow($order->id);
        $variance = app(ProductionAnalyticsService::class)->costVariance(ReportPeriod::preset('this_year'), ['scope' => 'completed']);
        $varianceRow = $variance->mapRows($variance->query->get())->first();

        $this->assertSame(3000.0, $row['actual_cost']);
        $this->assertSame(2000.0, $row['contribution']);
        $this->assertSame(500.0, $varianceRow['waste_cost']);
        $this->assertSame(3000.0, $varianceRow['actual_cost']);
    }

    public function test_rework_issue_is_reported_separately_but_counted_once_in_actual_cost(): void
    {
        [$order, $line] = $this->order(5000);
        $po = $this->productionOrder($line, 1);
        $lot = $this->lot(100, 10);
        $this->postedIssue($po, [[$lot, 50]]);
        $this->postedIssue($po, [[$lot, 20, 'REWORK']]);

        $dataset = app(ProfitabilityReportingService::class)->orders(ReportPeriod::preset('this_year'));
        $raw = $dataset->query->get()->firstWhere('id', $order->id);

        $this->assertSame(700.0, (float) $raw->actual_cost);
        $this->assertSame(200.0, (float) $raw->rework_cost);
        $this->assertSame(4300.0, $this->orderRow($order->id)['contribution']);
    }

    public function test_product_model_aggregates_two_completed_orders(): void
    {
        foreach ([[6000, 2, 2000], [4000, 1, 1500]] as [$value, $quantity, $cost]) {
            [, $line] = $this->order($value, $quantity);
            $po = $this->productionOrder($line, $quantity);
            $this->postedIssue($po, [[$this->lot(100, $cost / 100), 100]]);
        }

        $dataset = app(ProfitabilityReportingService::class)->products(ReportPeriod::preset('this_year'));
        $milan = $dataset->mapRows($dataset->query->get())->first(fn ($row) => ($row['product']['text'] ?? $row['product']) === 'سرير ميلان');

        $this->assertSame(2, $milan['order_count']);
        $this->assertSame(3.0, $milan['quantity']);
        $this->assertSame(10000.0, $milan['commercial_value']);
        $this->assertSame(3500.0, $milan['actual_cost_to_date']);
        $this->assertSame(6500.0, $milan['contribution']);
        $this->assertSame(65.0, $milan['contribution_pct']);
    }

    public function test_split_production_uses_each_orders_released_quantity(): void
    {
        [$order, $line] = $this->order(20000, 20);
        $poA = $this->productionOrder($line, 8);
        $poB = $this->productionOrder($line, 12, 'IN_PROGRESS');
        $lot = $this->lot(500, 10);
        $this->postedIssue($poA, [[$lot, 80]]);
        $this->postedIssue($poB, [[$lot, 120]]);

        $variance = app(ProductionAnalyticsService::class)->costVariance(ReportPeriod::preset('this_year'), ['scope' => 'all']);
        $rows = $variance->mapRows($variance->query->get())->keyBy(fn ($row) => $row['production_order']['text']);
        $raw = app(ProfitabilityReportingService::class)->orders(ReportPeriod::preset('this_year'))->query->get()->firstWhere('id', $order->id);

        $this->assertSame(8, $rows[$poA->production_order_number]['released_quantity']);
        $this->assertSame(800.0, $rows[$poA->production_order_number]['actual_cost']);
        $this->assertSame(12, $rows[$poB->production_order_number]['released_quantity']);
        $this->assertSame(1200.0, $rows[$poB->production_order_number]['actual_cost']);
        $this->assertSame(20.0, (float) $raw->released_qty);
        $this->assertSame(2000.0, (float) $raw->actual_cost);
        $this->assertSame('PROVISIONAL', $raw->profitability_status);
    }

    public function test_partially_produced_order_is_provisional_without_final_contribution(): void
    {
        [$order, $line] = $this->order(10000, 10);
        $po = $this->productionOrder($line, 10, 'PARTIALLY_COMPLETED');
        $this->postedIssue($po, [[$this->lot(100, 20), 40]]);

        $row = $this->orderRow($order->id);

        $this->assertSame('تكلفة غير مكتملة', $row['profitability_status']['label']);
        $this->assertSame(800.0, $row['actual_cost']);
        $this->assertNull($row['contribution']);
        $this->assertNull($row['contribution_pct']);
    }

    public function test_completed_production_without_posted_material_is_flagged_not_final(): void
    {
        [$order, $line] = $this->order(4000);
        $this->productionOrder($line, 1);

        $row = $this->orderRow($order->id);
        $totals = app(ProfitabilityReportingService::class)->orders(ReportPeriod::preset('this_year'))->totals;

        $this->assertSame('تكلفة مفقودة', $row['profitability_status']['label']);
        $this->assertNull($row['actual_cost']);
        $this->assertNull($row['contribution']);
        $this->assertSame(1, $totals['missing_cost_count']);
        $this->assertNull($totals['contribution']);
    }

    public function test_cancelled_order_does_not_inflate_totals(): void
    {
        [, $line] = $this->order(3000);
        $this->postedIssue($this->productionOrder($line, 1), [[$this->lot(10, 100), 10]]);
        $this->order(99999, 1, 'CANCELLED');

        $totals = app(ProfitabilityReportingService::class)->orders(ReportPeriod::preset('this_year'))->totals;

        $this->assertSame(1, $totals['order_count']);
        $this->assertSame(3000.0, $totals['commercial_value']);
        $this->assertSame(2000.0, $totals['contribution']);
    }

    public function test_physical_customer_return_does_not_reduce_commercial_value(): void
    {
        [$order, $line] = $this->order(8000);
        $po = $this->productionOrder($line, 1);
        $this->postedIssue($po, [[$this->lot(10, 300), 10]]);
        CustomerReturn::create([
            'return_number' => 'CR-RPT-1', 'customer_order_id' => $order->id, 'production_order_id' => $po->id,
            'customer_order_line_id' => $line->id, 'quantity' => 1, 'reason_code' => 'DAMAGED', 'status' => 'RECEIVED',
            'reported_at' => now(), 'reported_by_user_id' => $this->admin->id,
        ]);

        $row = $this->orderRow($order->id);

        $this->assertSame(8000.0, $row['commercial_value']);
        $this->assertSame(5000.0, $row['contribution']);
    }

    public function test_historical_order_values_survive_master_data_renames(): void
    {
        [$order, $line] = $this->order(7000, 1, 'APPROVED_FOR_PRODUCTION', null, ProductModel::create(['model_code' => 'OLD-1', 'name_ar' => 'اسم قديم', 'category' => 'BED', 'is_active' => true]));
        $this->postedIssue($this->productionOrder($line, 1), [[$this->lot(10, 100), 10]]);
        ProductModel::whereKey($line->product_model_id)->update(['name_ar' => 'اسم جديد']);
        Supplier::query()->update(['name' => 'مورد بعد إعادة التسمية']);

        $row = $this->orderRow($order->id);
        $products = app(ProfitabilityReportingService::class)->products(ReportPeriod::preset('this_year'));
        $labels = $products->mapRows($products->query->get())->map(fn ($r) => $r['product']['text'] ?? $r['product']);

        $this->assertSame(7000.0, $row['commercial_value']);
        $this->assertSame(6000.0, $row['contribution']);
        $this->assertTrue($labels->contains('اسم جديد'));
    }
}
