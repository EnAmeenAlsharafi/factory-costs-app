<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeItem;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private ProductionRouting $routing;

    private ManufacturingRecipeVersion $recipeVersion;

    private int $orderSequence = 700;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::factory()->create([
            'role_id' => Role::where('name', 'production_manager')->first()->id,
            'is_active' => true,
        ]);
        $this->routing = ProductionRouting::where('routing_code', 'RTG-000001')->firstOrFail();

        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-PIPE-1', 'name' => 'Pipeline recipe', 'is_active' => true]);
        $this->recipeVersion = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);
        $material = Material::create([
            'code' => 'MAT-PIPE-001',
            'name_ar' => 'خشب سويدي',
            'material_category_id' => MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'خشب', 'is_active' => true])->id,
            'base_unit_id' => UnitOfMeasure::firstOrFail()->id,
            'is_active' => true,
        ]);
        ManufacturingRecipeItem::create([
            'recipe_version_id' => $this->recipeVersion->id,
            'item_type' => 'MATERIAL',
            'material_id' => $material->id,
            'quantity' => 2,
            'unit_id' => $material->base_unit_id,
            'waste_percentage' => 0,
            'sort_order' => 1,
        ]);
    }

    public function test_each_stage_page_lists_its_waiting_work(): void
    {
        $this->salesOrder('PENDING_PRODUCTION_REVIEW');
        $this->salesOrder('APPROVED_FOR_PRODUCTION');

        $this->actingAs($this->manager)->get(route('production.pipeline.index'))
            ->assertRedirect(route('production.pipeline.index', 'approve'));

        foreach (['approve', 'release', 'materials', 'assign'] as $stage) {
            $this->actingAs($this->manager)->get(route('production.pipeline.index', $stage))->assertOk();
        }

        $this->actingAs($this->manager)->get(route('production.pipeline.index', 'release'))
            ->assertSee('إطلاق المحدد للورشة')
            ->assertSee('وصفة معتمدة');
    }

    public function test_user_without_any_stage_permission_cannot_open_the_pipeline(): void
    {
        $worker = User::factory()->create([
            'role_id' => Role::where('name', 'production_worker')->first()->id,
            'department_id' => Department::where('code', 'CARPENTRY')->first()->id,
            'is_active' => true,
        ]);

        $this->actingAs($worker)->get(route('production.pipeline.index', 'approve'))->assertForbidden();
        $this->actingAs($worker)->post(route('production.pipeline.approve'), ['order_ids' => [1]])->assertForbidden();
    }

    public function test_approves_several_orders_at_once_and_skips_ones_no_longer_pending(): void
    {
        [$first] = $this->salesOrder('PENDING_PRODUCTION_REVIEW');
        [$second] = $this->salesOrder('PENDING_PRODUCTION_REVIEW');
        [$cancelled] = $this->salesOrder('CANCELLED');

        $this->actingAs($this->manager)
            ->post(route('production.pipeline.approve'), ['order_ids' => [$first->id, $second->id, $cancelled->id]])
            ->assertRedirect(route('production.pipeline.index', 'approve'))
            ->assertSessionHas('pipeline_result', fn (array $result) => count($result['done']) === 2 && isset($result['skipped'][$cancelled->order_number]));

        $this->assertSame('APPROVED_FOR_PRODUCTION', $first->fresh()->status);
        $this->assertSame('APPROVED_FOR_PRODUCTION', $second->fresh()->status);
        $this->assertSame('CANCELLED', $cancelled->fresh()->status);
    }

    public function test_releases_several_lines_on_one_routing_and_skips_a_line_without_recipe(): void
    {
        [, $firstLine] = $this->salesOrder('APPROVED_FOR_PRODUCTION');
        [, $secondLine] = $this->salesOrder('APPROVED_FOR_PRODUCTION');
        [, $lineWithoutRecipe] = $this->salesOrder('APPROVED_FOR_PRODUCTION', withRecipe: false);

        $this->actingAs($this->manager)->post(route('production.pipeline.release'), [
            'line_ids' => [$firstLine->id, $secondLine->id, $lineWithoutRecipe->id],
            'production_routing_id' => $this->routing->id,
            'priority' => 'URGENT',
        ])->assertSessionHas('pipeline_result', fn (array $result) => count($result['done']) === 2 && count($result['skipped']) === 1);

        foreach ([$firstLine, $secondLine] as $line) {
            $productionOrder = ProductionOrder::where('customer_order_line_id', $line->id)->sole();
            $this->assertSame('RELEASED', $productionOrder->status);
            $this->assertSame('URGENT', $productionOrder->priority);
            $this->assertSame(3, (int) $productionOrder->released_quantity);
            $this->assertTrue($productionOrder->operations()->where('status', 'READY')->exists());
        }

        // The failed line leaves nothing half-done behind.
        $this->assertDatabaseMissing('production_orders', ['customer_order_line_id' => $lineWithoutRecipe->id]);
    }

    public function test_requests_materials_for_several_orders_from_their_recipe(): void
    {
        $first = $this->releasedProductionOrder();
        $second = $this->releasedProductionOrder();
        $warehouse = Warehouse::active()->firstOrFail();

        $this->actingAs($this->manager)->post(route('production.pipeline.materials'), [
            'production_order_ids' => [$first->id, $second->id],
            'warehouse_id' => $warehouse->id,
        ])->assertSessionHas('pipeline_result', fn (array $result) => count($result['done']) === 2 && $result['skipped'] === []);

        foreach ([$first, $second] as $productionOrder) {
            $request = $productionOrder->materialRequests()->sole();
            $this->assertSame('SUBMITTED', $request->status);
            $this->assertSame($warehouse->id, $request->warehouse_id);
            $this->assertEquals(6.0, (float) $request->lines()->sole()->requested_quantity);
        }

        // A second run does not raise duplicate requests.
        $this->actingAs($this->manager)->post(route('production.pipeline.materials'), [
            'production_order_ids' => [$first->id],
            'warehouse_id' => $warehouse->id,
        ])->assertSessionHas('pipeline_result', fn (array $result) => $result['done'] === []);
        $this->assertSame(1, $first->materialRequests()->count());
    }

    public function test_assigns_several_operations_to_one_employee_or_spreads_them_by_workload(): void
    {
        $carpentry = Department::where('code', 'CARPENTRY')->firstOrFail();
        $workerRole = Role::where('name', 'production_worker')->first();
        $busyWorker = User::factory()->create(['role_id' => $workerRole->id, 'department_id' => $carpentry->id, 'is_active' => true]);
        User::factory()->create(['role_id' => $workerRole->id, 'department_id' => $carpentry->id, 'is_active' => true]);

        $carpentryOperation = fn (ProductionOrder $productionOrder) => $productionOrder->operations()->where('operation_code', 'OP_CARPENTRY')->firstOrFail();
        $first = $carpentryOperation($this->releasedProductionOrder());
        $second = $carpentryOperation($this->releasedProductionOrder());

        $this->actingAs($this->manager)->post(route('production.pipeline.assign'), [
            'operation_ids' => [$first->id, $second->id],
            'mode' => 'employee',
            'employee_id' => $busyWorker->id,
        ])->assertSessionHas('pipeline_result', fn (array $result) => count($result['done']) === 2);

        $this->assertSame($busyWorker->id, $first->fresh()->assigned_user_id);
        $this->assertSame('ASSIGNED', $second->fresh()->status);

        $third = $carpentryOperation($this->releasedProductionOrder());
        $this->actingAs($this->manager)->post(route('production.pipeline.assign'), [
            'operation_ids' => [$third->id],
            'mode' => 'auto',
        ]);

        // Goes to an idle carpentry worker, never to the one already holding two tasks.
        $autoAssignee = $third->fresh()->assignedUser;
        $this->assertNotSame($busyWorker->id, $autoAssignee->id);
        $this->assertSame($carpentry->id, $autoAssignee->department_id);
        $this->assertSame(0, $autoAssignee->assignedOperations()->whereKeyNot($third->id)->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])->count());
        $this->assertSame(1, ProductionOrderOperation::whereKey($third->id)->where('status', 'ASSIGNED')->count());
    }

    public function test_order_page_points_to_the_next_step_and_offers_inline_assignment(): void
    {
        $carpentry = Department::where('code', 'CARPENTRY')->firstOrFail();
        $worker = User::factory()->create([
            'role_id' => Role::where('name', 'production_worker')->first()->id,
            'department_id' => $carpentry->id,
            'is_active' => true,
            'name' => 'عامل نجارة للاختبار',
        ]);
        $productionOrder = $this->releasedProductionOrder();

        $this->actingAs($this->manager)->get(route('production.orders.show', $productionOrder))
            ->assertOk()
            ->assertSee('الخطوة التالية')
            ->assertSee('طلب الخامات')
            ->assertSee(route('production.operations.assign', $productionOrder->operations()->where('operation_code', 'OP_CARPENTRY')->firstOrFail()), false)
            ->assertSee($worker->name);

        $this->actingAs($this->manager)->post(route('production.pipeline.materials'), [
            'production_order_ids' => [$productionOrder->id],
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
        ]);

        $this->actingAs($this->manager)->get(route('production.orders.show', $productionOrder))
            ->assertSee('إسناد المراحل الجاهزة');
    }

    /**
     * @return array{0: CustomerOrder, 1: CustomerOrderLine}
     */
    private function salesOrder(string $status, bool $withRecipe = true): array
    {
        $this->orderSequence++;
        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000'.$this->orderSequence,
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => $status,
            'order_date' => now(),
            'requested_delivery_date' => now()->addDays(5),
            'total_amount' => 300,
            'created_by_user_id' => $this->manager->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'approved_recipe_version_id' => $withRecipe ? $this->recipeVersion->id : null,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 3,
            'unit_price' => 100,
            'total_price' => 300,
        ]);

        return [$order, $line];
    }

    private function releasedProductionOrder(): ProductionOrder
    {
        [, $line] = $this->salesOrder('APPROVED_FOR_PRODUCTION');
        $service = app(ProductionOrderService::class);
        $productionOrder = $service->createFromOrderLine($line, ['released_quantity' => 3]);

        return $service->releaseProductionOrder($productionOrder, $this->routing, $this->manager);
    }
}
