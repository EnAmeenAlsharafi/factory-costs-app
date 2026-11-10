<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOperationProgress;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionPointCategory;
use App\Models\ProductionRouting;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerPointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $worker;

    protected ProductionOrder $po;

    protected CustomerOrderLine $line;

    protected ProductionPointCategory $padded;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::factory()->create(['role_id' => Role::where('name', 'production_manager')->first()->id, 'is_active' => true]);
        $carpentry = Department::where('code', 'CARPENTRY')->firstOrFail();
        $carpentry->update(['awards_points' => true]);
        $this->worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'department_id' => $carpentry->id, 'is_active' => true]);
        $this->padded = ProductionPointCategory::where('name_ar', 'سرير مضخم')->firstOrFail();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000777', 'customer_id' => Customer::first()->id, 'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION', 'order_date' => now(), 'total_amount' => 1000, 'created_by_user_id' => $this->manager->id,
        ]);
        $model = ProductModel::factory()->create(['point_category_id' => $this->padded->id]);
        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-PTS-001', 'name' => 'وصفة اختبار', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);
        $this->line = CustomerOrderLine::create([
            'customer_order_id' => $order->id, 'product_model_id' => $model->id, 'approved_recipe_version_id' => $version->id, 'requested_width_cm' => 160, 'requested_length_cm' => 200,
            'quantity' => 10, 'unit_price' => 100, 'total_price' => 1000,
        ]);
        $service = app(ProductionOrderService::class);
        $this->po = $service->createFromOrderLine($this->line, ['released_quantity' => 10, 'manufacturing_recipe_version_id' => $version->id]);
        $service->releaseProductionOrder($this->po, ProductionRouting::where('routing_code', 'RTG-000001')->firstOrFail(), $this->manager);
    }

    private function carpentryOperation(): ProductionOrderOperation
    {
        $operation = $this->po->fresh()->operations->where('operation_code', 'OP_CARPENTRY')->firstOrFail();
        $operation->update(['assigned_user_id' => $this->worker->id, 'assigned_at' => now()]);

        return $operation;
    }

    public function test_finished_pieces_credit_the_assigned_worker_with_the_model_points_and_keep_the_rate(): void
    {
        $operation = $this->carpentryOperation();

        $this->actingAs($this->manager)->post(route('production.operations.progress', $operation), ['added_quantity' => 5])->assertRedirect();
        $this->padded->update(['points' => 3]);
        $this->post(route('production.operations.correct', $operation), ['new_completed_quantity' => 4, 'correction_notes' => 'خطأ في العد'])->assertRedirect();

        $records = ProductionOperationProgress::where('production_order_operation_id', $operation->id)->orderBy('id')->get();
        $this->assertSame([$this->worker->id, $this->worker->id], $records->pluck('worker_user_id')->all());
        $this->assertSame('6.00', $records[0]->points, '5 pieces × 1.2 at the time of recording');
        $this->assertSame('-3.00', $records[1]->points, 'the correction uses the rate in force when it is recorded');
    }

    public function test_a_custom_order_category_overrides_the_model_and_other_departments_earn_nothing(): void
    {
        $base = ProductionPointCategory::where('name_ar', 'قاعدة سرير حواجز')->firstOrFail();
        $this->line->update(['point_category_id' => $base->id]);
        $operation = $this->carpentryOperation();

        $this->actingAs($this->manager)->post(route('production.operations.progress', $operation), ['added_quantity' => 4])->assertRedirect();
        $this->assertSame('2.00', ProductionOperationProgress::where('production_order_operation_id', $operation->id)->value('points'));

        Department::where('code', 'CARPENTRY')->update(['awards_points' => false]);
        $this->post(route('production.operations.progress', $operation), ['added_quantity' => 1])->assertRedirect();
        $this->assertNull(ProductionOperationProgress::where('production_order_operation_id', $operation->id)->latest('id')->value('points'));
    }

    public function test_monthly_report_ranks_workers_by_points_and_exports_the_sheet(): void
    {
        $operation = $this->carpentryOperation();
        $this->actingAs($this->manager)->post(route('production.operations.progress', $operation), ['added_quantity' => 5])->assertRedirect();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('reports.workers.index', ['month' => now()->format('Y-m')]))
            ->assertOk()->assertSee($this->worker->name)->assertSee('مجموع النقاط');
        $this->get(route('reports.workers.show', ['worker' => $this->worker, 'month' => now()->format('Y-m')]))
            ->assertOk()->assertSee('سرير مضخم')->assertSee($this->po->production_order_number);
        $csv = $this->get(route('reports.workers.export', ['month' => now()->format('Y-m')]))->assertOk()->streamedContent();
        $this->assertStringContainsString($this->worker->name, $csv);
        $this->assertStringContainsString(',6,', $csv);
        $this->get(route('reports.index'))->assertOk()->assertSee('أداء الموظفين ونقاط المكافآت');
    }

    public function test_work_recorded_before_classification_is_credited_from_settings(): void
    {
        ProductModel::whereKey($this->line->product_model_id)->update(['point_category_id' => null]);
        $operation = $this->carpentryOperation();
        $this->actingAs($this->manager)->post(route('production.operations.progress', $operation), ['added_quantity' => 2])->assertRedirect();
        $this->assertNull(ProductionOperationProgress::where('production_order_operation_id', $operation->id)->value('points'));

        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin)->put(route('production.points.models'), ['models' => [$this->line->product_model_id => $this->padded->id]])->assertRedirect();
        $this->post(route('production.points.credit-unclassified'), ['month' => now()->format('Y-m')])->assertRedirect();

        $this->assertSame('2.40', ProductionOperationProgress::where('production_order_operation_id', $operation->id)->value('points'));
        $this->get(route('production.points.index'))->assertOk()->assertSee('سرير مضخم');
    }
}
