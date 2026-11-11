<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOperationBlock;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\ProductionOrderService;
use App\Services\StageDeadlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StageDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CustomerOrder $order;

    protected ProductionOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00')); // a Thursday
        $this->admin = User::where('username', 'admin')->firstOrFail();
        Department::where('code', 'CARPENTRY')->update(['target_days' => 2]);

        $manager = User::factory()->create(['role_id' => Role::where('name', 'production_manager')->first()->id, 'is_active' => true]);
        $this->order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000888', 'customer_id' => Customer::first()->id, 'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION', 'order_date' => now(), 'total_amount' => 1000, 'created_by_user_id' => $manager->id,
        ]);
        $recipe = ManufacturingRecipe::create(['recipe_code' => 'RCP-DL-001', 'name' => 'وصفة', 'is_active' => true]);
        $version = ManufacturingRecipeVersion::create(['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1, 'status' => 'APPROVED', 'is_active' => true]);
        $line = CustomerOrderLine::create([
            'customer_order_id' => $this->order->id, 'approved_recipe_version_id' => $version->id, 'requested_width_cm' => 160, 'requested_length_cm' => 200,
            'quantity' => 4, 'unit_price' => 100, 'total_price' => 400,
        ]);
        $service = app(ProductionOrderService::class);
        $this->po = $service->createFromOrderLine($line, ['released_quantity' => 4, 'manufacturing_recipe_version_id' => $version->id]);
        $service->releaseProductionOrder($this->po, ProductionRouting::where('routing_code', 'RTG-000001')->firstOrFail(), $manager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function carpentry(): ProductionOrderOperation
    {
        return $this->po->fresh()->operations->where('operation_code', 'OP_CARPENTRY')->firstOrFail()->load('workCenter.department', 'blocks');
    }

    public function test_working_days_skip_friday(): void
    {
        $deadlines = app(StageDeadlineService::class);

        $this->assertSame('2026-11-15 09:00', $deadlines->addWorkingDays(Carbon::parse('2026-11-12 09:00'), 2)->format('Y-m-d H:i'), 'Thursday + 2 working days skips Friday');
        $this->assertSame(1, $deadlines->workingDaysBetween(Carbon::parse('2026-11-12 09:00'), Carbon::parse('2026-11-14 10:00')), 'Thursday to Saturday counts only Saturday');
    }

    public function test_a_stage_keeps_the_target_it_arrived_with_and_turns_late_after_it(): void
    {
        $stage = $this->carpentry();
        $this->assertSame(2, $stage->target_days);
        $this->assertSame('2026-11-15 09:00', $stage->due_at->format('Y-m-d H:i'));
        $this->assertSame('on_track', $stage->deadlineStatus()['state']);

        Department::where('code', 'CARPENTRY')->update(['target_days' => 9]);
        $stage->touch();
        $this->assertSame(2, $stage->fresh()->target_days, 'changing the department later does not move this order');

        Carbon::setTestNow('2026-11-17 10:00');
        $status = $this->carpentry()->deadlineStatus();
        $this->assertSame('overdue', $status['state']);
        $this->assertSame('متأخر يومين', $status['label']);
    }

    public function test_time_stopped_on_a_blocker_does_not_count_against_the_department(): void
    {
        $stage = $this->carpentry();
        ProductionOperationBlock::create([
            'production_order_operation_id' => $stage->id, 'production_order_id' => $this->po->id, 'reason_code' => 'MATERIAL_SHORTAGE',
            'previous_status' => 'READY', 'blocked_by_user_id' => $this->admin->id, 'blocked_at' => Carbon::parse('2026-11-12 10:00'),
            'resolved_at' => Carbon::parse('2026-11-15 10:00'), 'is_active' => false,
        ]);

        Carbon::setTestNow('2026-11-16 08:00');
        $status = $this->carpentry()->deadlineStatus();
        $this->assertNotSame('overdue', $status['state']);
        $this->assertTrue($status['blocked']);
    }

    public function test_settings_report_and_order_journey_show_where_the_order_is_late(): void
    {
        Carbon::setTestNow('2026-11-18 10:00');

        $this->actingAs($this->admin)->get(route('production.department-times.index'))->assertOk()->assertSee('مدد الأقسام')->assertSee('1 متأخرة');
        $this->put(route('production.department-times.update'), ['departments' => [Department::where('code', 'UPHOLSTERY')->value('id') => ['target_days' => 5, 'warning_percent' => 75]]])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(5, Department::where('code', 'UPHOLSTERY')->value('target_days'));

        $this->get(route('reports.production.delays'))->assertOk()->assertSee($this->po->production_order_number)->assertSee('متأخر');
        $this->get(route('sales.orders.show', $this->order))->assertOk()->assertSee('متأخر في النجارة');
        $this->get(route('production.orders.show', $this->po))->assertOk()->assertSee('deadline-chip', false);
    }
}
