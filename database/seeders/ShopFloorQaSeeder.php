<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\ProductionRouting;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\DeliveryOrderService;
use App\Services\DocumentNumberService;
use App\Services\FinishedGoodsService;
use App\Services\ProductionAssignmentService;
use App\Services\ProductionExecutionService;
use App\Services\ProductionOrderService;
use App\Services\QualityIncidentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Stage 16.5 — Shop Floor Execution QA fixtures (dev/testing only).
 *
 * Builds a representative set of scenarios for manual QA of the execution
 * state machine, assignment, blocking, rework, and end-to-end order
 * tracking: a linear flow, a parallel-branch flow, a partially-completed
 * operation, a blocked operation, an open rework case, and two completed
 * orders — one just assigned for delivery, one delivered and installed.
 *
 * NEVER run outside local/testing. QA account passwords follow a per-account
 * scheme (not a single shared default — spec §88/§89) documented below.
 */
class ShopFloorQaSeeder extends Seeder
{
    /**
     * QA accounts are reused from DevelopmentSeeder where one already exists
     * for the department (CARPENTRY, production_manager, delivery_user);
     * every other department gets a dedicated account here. Password scheme:
     * "Qa#<username>#2026" — unique per account, not a single shared default.
     */
    private const DEPARTMENT_WORKER_USERNAMES = [
        'CARPENTRY' => 'worker.carpenter', // already seeded by DevelopmentSeeder
        'FOAM' => 'qa.worker.foam',
        'UPHOLSTERY' => 'qa.worker.upholstery',
        'BOX_PRODUCTION' => 'qa.worker.box_prod',
        'BOX_PREPARATION' => 'qa.worker.box_prep',
        'ASSEMBLY' => 'qa.worker.assembly',
        'PACKAGING' => 'qa.worker.packaging',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $orderService = app(ProductionOrderService::class);
        $executionService = app(ProductionExecutionService::class);
        $assignmentService = app(ProductionAssignmentService::class);
        $finishedGoodsService = app(FinishedGoodsService::class);
        $deliveryService = app(DeliveryOrderService::class);

        $manager = $this->qaManager();
        $workers = $this->departmentWorkers();
        $driver = $this->qaDeliveryDriver();

        $routing = ProductionRouting::where('routing_code', 'RTG-000001')->first();
        $recipeVersion = $this->qaRecipeVersion();

        if (! $routing) {
            $this->command?->warn('ShopFloorQaSeeder: RTG-000001 routing not found — skipping (run WorkCenterSeeder/DefaultBedRoutingSeeder first).');

            return;
        }

        // 1. Linear flow — every operation claimed, started and completed in sequence.
        $linearPo = $this->releasedProductionOrder('QA-LINEAR', $recipeVersion, $routing, $manager, 10);
        foreach (['OP_CARPENTRY', 'OP_FOAM', 'OP_UPHOLSTERY', 'OP_BOX_PROD', 'OP_BOX_PREP', 'OP_ASSEMBLY', 'OP_PACKAGING'] as $code) {
            $op = $this->findOp($linearPo, $code);
            $worker = $workers[$op->workCenter->department->code] ?? $manager;
            $assignmentService->claim($op->fresh(), $worker);
            $executionService->start($op->fresh(), $worker);
            $executionService->complete($op->fresh(), $worker, 'إنجاز تجريبي QA — المسار الخطي');
        }

        // 2. Parallel flow — branch A ahead of branch B, so the join at ASSEMBLY is still PENDING.
        $parallelPo = $this->releasedProductionOrder('QA-PARALLEL', $recipeVersion, $routing, $manager, 15);
        $this->progress($orderService, $parallelPo, 'OP_CARPENTRY', 15, $manager);
        $this->progress($orderService, $parallelPo, 'OP_FOAM', 15, $manager);
        $this->progress($orderService, $parallelPo, 'OP_UPHOLSTERY', 15, $manager);
        $this->progress($orderService, $parallelPo, 'OP_BOX_PROD', 15, $manager);
        $this->progress($orderService, $parallelPo, 'OP_BOX_PREP', 6, $manager); // branch B lags — ASSEMBLY capped at 6.

        // 3. Partial operation — carpentry half-done, still PARTIALLY_COMPLETED.
        $partialPo = $this->releasedProductionOrder('QA-PARTIAL', $recipeVersion, $routing, $manager, 12);
        $this->progress($orderService, $partialPo, 'OP_CARPENTRY', 6, $manager);

        // 4. Blocked operation — left blocked for QA to exercise resolve-block.
        $blockedPo = $this->releasedProductionOrder('QA-BLOCKED', $recipeVersion, $routing, $manager, 8);
        $blockedOp = $this->findOp($blockedPo, 'OP_CARPENTRY');
        $blockedWorker = $workers['CARPENTRY'];
        $executionService->start($blockedOp->fresh(), $blockedWorker);
        $executionService->block($blockedOp->fresh(), 'MATERIAL_SHORTAGE', 'نقص في ألواح الخشب — بانتظار التوريد (بيانات QA)', $blockedWorker);

        // 5. Open rework — carpentry completed, then a quality incident sends it back for rework (not yet completed).
        $reworkPo = $this->releasedProductionOrder('QA-REWORK', $recipeVersion, $routing, $manager, 10);
        $reworkOp = $this->findOp($reworkPo, 'OP_CARPENTRY');
        $this->progress($orderService, $reworkPo, 'OP_CARPENTRY', 10, $manager);
        $this->openRework($reworkOp->fresh(), $manager);

        // 6. Fully completed order, FG received, delivery just assigned (not yet dispatched).
        $assignedDeliveryPo = $this->completedProductionOrder('QA-DELIVERY-ASSIGNED', $recipeVersion, $routing, $manager, $executionService, $workers);
        $receipt = $finishedGoodsService->createReceipt($assignedDeliveryPo, $manager, ['received_quantity' => $assignedDeliveryPo->released_quantity]);
        $finishedGoodsService->postReceipt($receipt, $manager);
        $delivery = $deliveryService->createDeliveryOrder($assignedDeliveryPo->customerOrder, $manager, [
            'installation_required' => true,
            'lines' => [[
                'production_order_id' => $assignedDeliveryPo->id,
                'customer_order_line_id' => $assignedDeliveryPo->customer_order_line_id,
                'quantity' => $assignedDeliveryPo->released_quantity,
            ]],
        ]);
        $deliveryService->markReady($delivery, $manager);
        $deliveryService->assignDriver($delivery->fresh(), $driver, $manager);

        // 7. Fully completed, delivered and installed — shows "مكتمل تشغيلياً" end to end.
        $fulfilledPo = $this->completedProductionOrder('QA-FULFILLED', $recipeVersion, $routing, $manager, $executionService, $workers);
        $fulfilledReceipt = $finishedGoodsService->createReceipt($fulfilledPo, $manager, ['received_quantity' => $fulfilledPo->released_quantity]);
        $finishedGoodsService->postReceipt($fulfilledReceipt, $manager);
        $fulfilledDelivery = $deliveryService->createDeliveryOrder($fulfilledPo->customerOrder, $manager, [
            'installation_required' => true,
            'lines' => [[
                'production_order_id' => $fulfilledPo->id,
                'customer_order_line_id' => $fulfilledPo->customer_order_line_id,
                'quantity' => $fulfilledPo->released_quantity,
            ]],
        ]);
        $deliveryService->markReady($fulfilledDelivery, $manager);
        $fulfilledDelivery = $deliveryService->assignDriver($fulfilledDelivery->fresh(), $driver, $manager);
        $fulfilledDelivery = $deliveryService->dispatchDelivery($fulfilledDelivery->fresh(), $driver);
        $fulfilledDelivery = $deliveryService->markDelivered($fulfilledDelivery->fresh(), $driver, 'تم التسليم (بيانات QA)');
        $deliveryService->markInstalled($fulfilledDelivery->fresh(), $driver, 'تم التركيب والمعاينة (بيانات QA)');

        $this->command?->info('ShopFloorQaSeeder: 7 QA production orders seeded (linear, parallel, partial, blocked, rework, delivery-assigned, fulfilled).');
    }

    private function qaManager(): User
    {
        $role = Role::where('name', 'production_manager')->first();

        return User::updateOrCreate(
            ['username' => 'prod.manager'],
            ['name' => 'م. خالد - مدير الإنتاج', 'email' => 'production@sadir-factory.com', 'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true]
        );
    }

    private function qaDeliveryDriver(): User
    {
        $role = Role::where('name', 'delivery_user')->first();

        return User::updateOrCreate(
            ['username' => 'delivery.driver'],
            ['name' => 'سامي - سائق التوصيل', 'email' => 'delivery@sadir-factory.com', 'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true]
        );
    }

    /**
     * @return array<string, User> department code => worker user
     */
    private function departmentWorkers(): array
    {
        $workerRole = Role::where('name', 'production_worker')->first();
        $workers = [];

        foreach (self::DEPARTMENT_WORKER_USERNAMES as $deptCode => $username) {
            $department = Department::where('code', $deptCode)->first();

            if (! $department) {
                continue;
            }

            // An account reused from DevelopmentSeeder (e.g. worker.carpenter) keeps its own
            // existing credentials untouched — only a genuinely new QA account gets created here.
            $existing = User::where('username', $username)->first();

            $workers[$deptCode] = $existing ?? User::create([
                'username' => $username,
                'name' => "موظف QA — قسم {$department->name_ar}",
                'email' => "{$username}@sadir-factory.qa",
                'password' => Hash::make('Qa#'.$username.'#2026'),
                'role_id' => $workerRole->id,
                'department_id' => $department->id,
                'is_active' => true,
            ]);
        }

        return $workers;
    }

    private function qaRecipeVersion(): ManufacturingRecipeVersion
    {
        $recipe = ManufacturingRecipe::firstOrCreate(
            ['recipe_code' => 'RCP-QA-STAGE165'],
            ['name' => 'وصفة بيانات QA — المرحلة 16.5', 'is_active' => true]
        );

        return ManufacturingRecipeVersion::firstOrCreate(
            ['manufacturing_recipe_id' => $recipe->id, 'version_number' => 1],
            ['status' => 'APPROVED']
        );
    }

    private function releasedProductionOrder(
        string $tag,
        ManufacturingRecipeVersion $recipeVersion,
        ProductionRouting $routing,
        User $manager,
        int $quantity
    ): ProductionOrder {
        $order = CustomerOrder::create([
            'order_number' => DocumentNumberService::generateOrderNumber(),
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => $quantity * 100,
            'commercial_notes' => "طلب بيانات QA — {$tag}",
            'created_by_user_id' => $manager->id,
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => $quantity,
            'unit_price' => 100,
            'line_total' => $quantity * 100,
        ]);

        $service = app(ProductionOrderService::class);
        $po = $service->createFromOrderLine($line, [
            'released_quantity' => $quantity,
            'manufacturing_recipe_version_id' => $recipeVersion->id,
        ]);

        $service->releaseProductionOrder($po, $routing, $manager);

        return $po->fresh('operations.workCenter.department');
    }

    private function completedProductionOrder(
        string $tag,
        ManufacturingRecipeVersion $recipeVersion,
        ProductionRouting $routing,
        User $manager,
        ProductionExecutionService $executionService,
        array $workers
    ): ProductionOrder {
        $po = $this->releasedProductionOrder($tag, $recipeVersion, $routing, $manager, 6);

        foreach (['OP_CARPENTRY', 'OP_FOAM', 'OP_UPHOLSTERY', 'OP_BOX_PROD', 'OP_BOX_PREP', 'OP_ASSEMBLY', 'OP_PACKAGING'] as $code) {
            $op = $this->findOp($po, $code);
            $worker = $workers[$op->workCenter->department->code] ?? $manager;
            $executionService->complete($op->fresh(), $worker);
        }

        return $po->fresh();
    }

    private function findOp(ProductionOrder $po, string $code): ProductionOrderOperation
    {
        return $po->operations->where('operation_code', $code)->first()->fresh(['workCenter.department']);
    }

    private function progress(ProductionOrderService $service, ProductionOrder $po, string $code, int $quantity, User $actor): void
    {
        $service->recordProgress($this->findOp($po, $code), $quantity, 'PROGRESS', 'بيانات QA', $actor);
    }

    private function openRework(ProductionOrderOperation $op, User $manager): ProductionReworkAction
    {
        $incidentService = app(QualityIncidentService::class);
        $department = $op->workCenter->department;

        $incident = QualityIncident::create([
            'incident_number' => DocumentNumberService::generateIncidentNumber(),
            'production_order_id' => $op->production_order_id,
            'production_order_operation_id' => $op->id,
            'affected_quantity' => 2,
            'detected_department_id' => $department->id,
            'responsible_department_id' => $department->id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'عيب في التجميع — بيانات QA',
            'status' => 'ACTION_REQUIRED',
            'detected_by_user_id' => $manager->id,
        ]);

        return $incidentService->createReworkAction($incident, $manager, [
            'source_operation_id' => $op->id,
            'target_operation_id' => $op->id,
            'action_type' => 'REWORK',
            'quantity' => 2,
            'notes' => 'إعادة تجميع القطعة المعيبة — بيانات QA',
        ]);
    }
}
