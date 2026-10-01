<?php

namespace Tests\Feature\Reports;

use App\Domain\Reports\InventoryAnalyticsService;
use App\Domain\Reports\ProcurementAnalyticsService;
use App\Domain\Reports\ProductionAnalyticsService;
use App\Domain\Reports\ProfitabilityReportingService;
use App\Domain\Reports\ReceivablesAnalyticsService;
use App\Domain\Reports\ReportPeriod;
use App\Models\Customer;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerType;
use App\Models\Permission;
use App\Models\ProductionOrder;
use App\Models\Role;
use App\Services\ProductionCostService;
use App\Services\ReceivablesReportingService;

class ReportAccessExportReconciliationTest extends ReportingTestCase
{
    public function test_operational_roles_are_refused_management_reports(): void
    {
        foreach (['production_worker', 'delivery_user', 'customer_service'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.dashboard'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.profitability.orders'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.receivables'))->assertForbidden();
        }
    }

    public function test_purchasing_user_sees_procurement_but_not_profitability(): void
    {
        $purchasing = $this->userWithRole('purchasing_user');

        $this->actingAs($purchasing)->get(route('reports.procurement'))->assertOk()->assertSee('انحراف أسعار الشراء');
        $this->actingAs($purchasing)->get(route('reports.profitability.orders'))->assertForbidden();
        $this->actingAs($purchasing)->get(route('reports.index'))->assertOk()->assertDontSee('ربحية الطلبات التشغيلية');
    }

    public function test_warehouse_keeper_sees_stock_without_operational_valuation(): void
    {
        $keeper = $this->userWithRole('warehouse_keeper');
        $this->lot(10, 20);

        $this->actingAs($keeper)->get(route('reports.inventory', ['tab' => 'valuation']))
            ->assertOk()
            ->assertSee('أرصدة المواد الخام')
            ->assertDontSee('القيمة التشغيلية (تكلفة اللوت)')
            ->assertDontSee('200.00 ر.س');
    }

    public function test_production_manager_gets_cost_variance_but_no_profitability_or_receivables(): void
    {
        $manager = $this->userWithRole('production_manager');

        $this->actingAs($manager)->get(route('reports.production.variance'))->assertOk();
        $this->actingAs($manager)->get(route('reports.profitability.orders'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.dashboard'))->assertOk()
            ->assertDontSee('المساهمة التشغيلية')
            ->assertDontSee('المبالغ المستحقة');
    }

    public function test_sales_user_sees_commercial_values_without_cost_or_contribution_columns(): void
    {
        [, $line] = $this->order(9000);
        $this->postedIssue($this->productionOrder($line, 1), [[$this->lot(10, 100), 10]]);
        $sales = $this->userWithRole('sales_user');

        $this->actingAs($sales)->get(route('reports.profitability.products', ['period' => 'this_year']))
            ->assertOk()
            ->assertSee('9,000.00')
            ->assertDontSee('تكلفة المواد الفعلية حتى تاريخه')
            ->assertDontSee('المساهمة التشغيلية (المكتملة)');
        $this->actingAs($sales)->get(route('reports.profitability.orders'))->assertForbidden();
    }

    public function test_export_requires_permission_and_honours_filters_and_visible_columns(): void
    {
        [$included] = $this->order(4000);
        $otherCustomer = Customer::create([
            'customer_code' => 'CUST-RPT-OTHER',
            'name' => 'عميل آخر للتصفية',
            'customer_type_id' => CustomerType::orderBy('id')->value('id'),
            'is_active' => true,
        ]);
        [$excluded] = $this->order(5000);
        $excluded->update(['customer_id' => $otherCustomer->id]);

        $sales = $this->userWithRole('sales_user');
        $this->actingAs($sales)->get(route('reports.profitability.products', ['export' => 'csv']))->assertForbidden();

        $response = $this->actingAs($this->admin)->get(route('reports.profitability.orders', [
            'period' => 'this_year', 'customer_id' => $included->customer_id, 'export' => 'csv',
        ]));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($included->order_number, $csv);
        $this->assertStringNotContainsString($excluded->order_number, $csv);
        $this->assertStringContainsString('تكلفة المواد الفعلية', $csv);

        $role = Role::where('name', 'sales_user')->firstOrFail();
        $role->permissions()->syncWithoutDetaching(Permission::where('name', 'reports.export')->pluck('id'));
        $salesCsv = $this->actingAs($sales->fresh())->get(route('reports.profitability.products', ['period' => 'this_year', 'export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('القيمة التجارية', $salesCsv);
        $this->assertStringNotContainsString('تكلفة المواد الفعلية', $salesCsv);
        $this->assertStringNotContainsString('المساهمة التشغيلية', $salesCsv);
    }

    public function test_receivable_due_45_days_ago_falls_in_31_to_60_bucket(): void
    {
        [$order] = $this->order(10000);
        $order->update(['payment_due_date' => now()->subDays(45)->toDateString()]);

        $buckets = app(ReceivablesAnalyticsService::class)->agingBuckets();

        $this->assertSame(10000.0, $buckets['31_60']['amount']);
        $this->assertSame(0.0, $buckets['1_30']['amount']);
        $this->actingAs($this->admin)->get(route('reports.receivables'))->assertOk()->assertSee('31 - 60 يوم');
    }

    public function test_report_totals_reconcile_with_authoritative_services(): void
    {
        [$orderA, $lineA] = $this->order(6000);
        [$orderB, $lineB] = $this->order(3000);
        $poA = $this->productionOrder($lineA, 1);
        $poB = $this->productionOrder($lineB, 1, 'IN_PROGRESS');
        $lotA = $this->lot(50, 20);
        $lotB = $this->lot(30, 25);
        $this->postedIssue($poA, [[$lotA, 20], [$lotB, 10]]);
        $this->postedReturn($poA, $lotA, 5);
        $this->postedIssue($poB, [[$lotB, 8]]);
        $orderB->update(['payment_due_date' => now()->subDays(10)->toDateString()]);
        $period = ReportPeriod::preset('this_year');

        // Orders: commercial totals equal the historical order totals.
        $totals = app(ProfitabilityReportingService::class)->orders($period)->totals;
        $this->assertSame(9000.0, $totals['commercial_value']);

        // Production: report actual cost equals the Stage 10 service for every production order.
        $variance = app(ProductionAnalyticsService::class)->costVariance($period, ['scope' => 'all']);
        $reported = $variance->mapRows($variance->query->get())->sum('actual_cost');
        $authoritative = ProductionOrder::all()->sum(fn ($po) => app(ProductionCostService::class)->calculateOrderMaterialCost($po)['actual_net_material_cost']);
        $this->assertEqualsWithDelta($authoritative, $reported, 0.01);

        // Inventory: value equals Σ remaining × lot cost.
        $this->assertEqualsWithDelta(50 * 20 + 30 * 25, app(InventoryAnalyticsService::class)->totalStockValue(), 0.01);

        // Receivables: customer outstanding in the analysis equals the Stage 13 balances.
        $customerId = $orderA->customer_id;
        $analysis = app(ProfitabilityReportingService::class)->customers($period);
        $reportedOutstanding = $analysis->mapRows($analysis->query->get())->firstWhere('customer_id', $customerId)['outstanding'];
        $stageThirteen = collect(app(ReceivablesReportingService::class)->getCustomerBalances([], 100)->items())
            ->first(fn ($row) => $row['customer']->id === $customerId)['outstanding_balance'];
        $this->assertEqualsWithDelta($stageThirteen, $reportedOutstanding, 0.01);

        // Purchasing: received value equals posted receipt lines of the period (none in this fixture).
        $this->assertSame(0.0, app(ProcurementAnalyticsService::class)->summary($period)['received_value']);
    }

    public function test_credit_orders_of_a_customer_without_profile_do_not_break_dashboards(): void
    {
        foreach ([1000, 2000] as $value) {
            [$order] = $this->order($value);
            $order->update(['payment_terms_type' => 'CREDIT']);
        }

        $this->actingAs($this->admin)->get(route('reports.dashboard'))->assertOk();
        $this->actingAs($this->admin)->get(route('reports.exceptions'))->assertOk();
        $this->actingAs($this->admin)->get(route('receivables.dashboard'))->assertOk();
        $this->assertSame(1, CustomerCreditProfile::count());
    }

    public function test_every_report_page_renders_for_administrator_without_misleading_terms(): void
    {
        [, $line] = $this->order(5000);
        $this->postedIssue($this->productionOrder($line, 1), [[$this->lot(10, 100), 10]]);

        foreach ([
            route('reports.index'), route('reports.dashboard'), route('reports.exceptions'),
            route('reports.profitability.orders', ['period' => 'this_year']), route('reports.profitability.products'),
            route('reports.profitability.channels'), route('reports.profitability.customers'),
            route('reports.production.variance', ['scope' => 'all']), route('reports.production.recipes'),
            route('reports.production.cost-structure'), route('reports.production.quality'),
            route('reports.production.quality', ['tab' => 'rework']), route('reports.production.quality', ['tab' => 'quality']),
            route('reports.production.departments'), route('reports.inventory'), route('reports.inventory', ['tab' => 'fabric']),
            route('reports.inventory', ['tab' => 'slow']), route('reports.inventory', ['tab' => 'shortages']),
            route('reports.procurement'), route('reports.procurement.price-history', ['material_id' => $this->material->id]),
            route('reports.fulfillment'), route('reports.fulfillment', ['tab' => 'finished_goods']),
            route('reports.fulfillment', ['tab' => 'delivery']), route('reports.fulfillment', ['tab' => 'returns']),
            route('reports.receivables'), route('reports.receivables', ['tab' => 'collections']),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertDontSee('صافي الربح')->assertDontSee('Net Profit');
        }
    }
}
