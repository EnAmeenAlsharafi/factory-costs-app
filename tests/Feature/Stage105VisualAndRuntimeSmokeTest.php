<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductModel;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage105VisualAndRuntimeSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $productionManager;

    protected User $warehouseKeeper;

    protected User $worker;

    protected User $customerService;

    protected ProductionOrder $productionOrder;

    protected CustomerOrder $customerOrder;

    protected Quotation $quotation;

    protected Material $material;

    protected ProductModel $productModel;

    protected Department $carpentryDept;

    protected ProductionMaterialRequest $materialRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $roles = Role::all()->keyBy('name');
        $this->carpentryDept = Department::where('code', 'CARPENTRY')->first();
        $whDept = Department::where('code', 'WAREHOUSE')->first();

        $this->admin = User::factory()->create(['role_id' => $roles['admin']->id, 'is_active' => true]);
        $this->productionManager = User::factory()->create(['role_id' => $roles['production_manager']->id, 'is_active' => true]);
        $this->warehouseKeeper = User::factory()->create(['role_id' => $roles['warehouse_keeper']->id, 'department_id' => $whDept->id, 'is_active' => true]);
        $this->worker = User::factory()->create(['role_id' => $roles['production_worker']->id, 'department_id' => $this->carpentryDept->id, 'is_active' => true]);
        $this->customerService = User::factory()->create(['role_id' => $roles['customer_service']->id, 'is_active' => true]);

        // Seed domain entities for routes requiring parameters
        $customer = Customer::first();
        $salesChannel = SalesChannel::first();
        $unit = UnitOfMeasure::first();
        $cat = MaterialCategory::first();
        $warehouse = Warehouse::first();

        $this->material = Material::create([
            'code' => 'MAT-TEST-001',
            'name_ar' => 'خشب زان اختبار',
            'material_category_id' => $cat->id,
            'base_unit_id' => $unit->id,
            'material_type' => 'WOOD',
            'is_active' => true,
        ]);

        $this->productModel = ProductModel::create([
            'model_code' => 'MOD-TEST-001',
            'name_ar' => 'سرير كلاسيك اختبار',
            'is_active' => true,
        ]);

        $this->quotation = Quotation::create([
            'quotation_number' => 'QUO-2026-000099',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'DRAFT',
            'quotation_date' => now(),
            'valid_until' => now()->addDays(14),
            'total_amount' => 1500.00,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->customerOrder = CustomerOrder::create([
            'order_number' => 'ORD-2026-000099',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1500.00,
            'created_by_user_id' => $this->admin->id,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $this->customerOrder->id,
            'item_number' => 1,
            'product_model_id' => $this->productModel->id,
            'requested_width_cm' => 180,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000099',
            'customer_order_id' => $this->customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 1,
            'released_quantity' => 1,
            'status' => 'IN_PROGRESS',
        ]);

        $workCenter = WorkCenter::where('department_id', $this->carpentryDept->id)->first();
        if ($workCenter) {
            ProductionOrderOperation::create([
                'production_order_id' => $this->productionOrder->id,
                'operation_code' => 'OP-CARPENTRY',
                'operation_name_snapshot' => 'قص وتجهيز الخشب',
                'sequence_number' => 10,
                'department_id' => $this->carpentryDept->id,
                'work_center_id' => $workCenter->id,
                'planned_quantity' => 1,
                'completed_quantity' => 0,
                'status' => 'READY',
            ]);
        }

        $this->materialRequest = ProductionMaterialRequest::create([
            'request_number' => 'PMR-2026-000099',
            'production_order_id' => $this->productionOrder->id,
            'requested_from_department_id' => $this->carpentryDept->id,
            'requested_by_user_id' => $this->admin->id,
            'warehouse_id' => $warehouse->id,
            'request_date' => now()->toDateString(),
            'status' => 'SUBMITTED',
        ]);

        $this->materialRequest->lines()->create([
            'material_id' => $this->material->id,
            'requested_quantity' => 5,
            'approved_quantity' => 5,
            'issued_quantity' => 0,
            'base_unit_id' => $unit->id,
            'request_reason' => 'PLANNED_PRODUCTION',
        ]);
    }

    /**
     * Test all primary GET routes load cleanly (HTTP 200) for Admin user.
     */
    public function test_all_admin_get_routes_render_cleanly(): void
    {
        $routes = [
            route('dashboard'),
            route('users.index'),
            route('users.create'),
            route('roles.index'),
            route('sales-channels.index'),
            route('customer-types.index'),
            route('customers.index'),
            route('customers.create'),
            route('suppliers.index'),
            route('suppliers.create'),
            route('units.index'),
            route('departments.index'),
            route('materials.index'),
            route('materials.create'),
            route('inventory.balances.index'),
            route('inventory.receipts.index'),
            route('inventory.issues.index'),
            route('inventory.returns.index'),
            route('inventory.adjustments.index'),
            route('inventory.adjustments.create'),
            route('products.models.index'),
            route('products.models.create'),
            route('products.sizes.index'),
            route('recipes.index'),
            route('recipes.create'),
            route('recipes.templates.index'),
            route('recipes.templates.create'),
            route('recipes.components.index'),
            route('sales.quotations.index'),
            route('sales.quotations.create'),
            route('sales.orders.index'),
            route('sales.orders.create'),
            route('sales.orders.review', $this->customerOrder),
            route('production.orders.index'),
            route('production.orders.show', $this->productionOrder),
            route('production.routings.index'),
            route('production.queue.index'),
            route('production.board.index'),
            route('production.material-requests.index'),
            route('production.material-requests.create', ['production_order_id' => $this->productionOrder->id]),
            route('production.material-requests.show', $this->materialRequest),
            route('production.material-requests.fulfill.form', $this->materialRequest),
            route('production.quality-incidents.index'),
            route('production.quality-incidents.create', ['production_order_id' => $this->productionOrder->id]),
            route('production.waste.index'),
            route('production.waste.create', ['production_order_id' => $this->productionOrder->id]),
            route('production.rework.index'),
            route('production.reports.index'),
            route('production.reports.cost'),
            route('production.reports.waste'),
            route('production.reports.quality'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->admin)->get($url);
            if ($response->status() !== 200) {
                $this->fail("Route URL [{$url}] returned status {$response->status()}");
            }
            $response->assertSee('dir="rtl"', false);
            $response->assertSee('lang="ar"', false);
        }
    }

    /**
     * Test specifically previously problematic pages.
     */
    public function test_previously_problematic_pages_render_without_errors(): void
    {
        // 1. /recipes/templates
        $response = $this->actingAs($this->admin)->get(route('recipes.templates.index'));
        $response->assertOk();
        $response->assertSee('قوالب التصنيع');

        // 2. /recipes/components
        $response = $this->actingAs($this->admin)->get(route('recipes.components.index'));
        $response->assertOk();
        $response->assertSee('المكونات نصف المصنعة');

        // 3. /production/queue
        $response = $this->actingAs($this->worker)->get(route('production.queue.index'));
        $response->assertOk();
        $response->assertSee('أعمال الأقسام');

        // 4. /sales/orders/{order}/review
        $response = $this->actingAs($this->productionManager)->get(route('sales.orders.review', $this->customerOrder));
        $response->assertOk();
        $response->assertSee('اعتماد الطلب');

        // 5. /production/material-requests/create
        $response = $this->actingAs($this->productionManager)->get(route('production.material-requests.create', ['production_order_id' => $this->productionOrder->id]));
        $response->assertOk();
        $response->assertSee('بنود المواد المطلوبة');

        // 6. /inventory/adjustments
        $response = $this->actingAs($this->admin)->get(route('inventory.adjustments.index'));
        $response->assertOk();
        $response->assertSee('تسويات المخزون');

        // 7. /inventory/adjustments/create
        $response = $this->actingAs($this->admin)->get(route('inventory.adjustments.create'));
        $response->assertOk();
        $response->assertSee('تسوية');
    }

    /**
     * Test cost restriction masking for non-costing role (Worker).
     */
    public function test_cost_masking_for_worker_role(): void
    {
        // Worker must be forbidden from accessing production cost report
        $response = $this->actingAs($this->worker)->get(route('production.reports.cost'));
        $response->assertForbidden();

        // Worker accessing production order detail should not see cost figures
        $response = $this->actingAs($this->worker)->get(route('production.orders.show', $this->productionOrder));
        $response->assertOk();
        $response->assertDontSee('تكلفة الخامة الإجمالية');
    }

    /**
     * Test department queue visual scoping for worker.
     */
    public function test_department_queue_scoping_for_worker(): void
    {
        $response = $this->actingAs($this->worker)->get(route('production.queue.index'));
        $response->assertOk();
        $response->assertSee($this->carpentryDept->name_ar);
    }
}
