<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\ProductConfiguration;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\ProductionRouting;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\QualityIncident;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FinishedGoodsService;
use App\Services\InventoryService;
use App\Services\ProductionMaterialRequestService;
use App\Services\ProductionOrderService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceivingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Stage 14 management-reporting QA dataset (local development only; never registered in DatabaseSeeder).
 *
 * Creates, through the real services: a profitable completed order, a negative-contribution order, a partially
 * produced order, a split production line, waste, rework with extra material, a usable material return,
 * purchase-price variance, a late partially received PO, finished goods waiting delivery and an overdue receivable.
 *
 * Run after MobileQaSeeder: php artisan db:seed --class=ManagementReportingQaSeeder
 */
class ManagementReportingQaSeeder extends Seeder
{
    private const MARKER = 'ORD-QA14-PROFIT';

    private User $manager;

    private Warehouse $warehouse;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('ManagementReportingQaSeeder runs only in the local environment.');

            return;
        }
        if (CustomerOrder::where('order_number', self::MARKER)->exists()) {
            $this->command?->info('Management reporting QA data already present.');

            return;
        }

        DB::transaction(fn () => $this->seedDataset());
        $this->command?->info('Management reporting QA data created.');
    }

    private function seedDataset(): void
    {
        $this->manager = User::where('username', 'prod.manager')->firstOrFail();
        $this->warehouse = Warehouse::where('code', '!=', 'FINISHED_GOODS')->orderBy('id')->firstOrFail();
        $supplierA = Supplier::orderBy('id')->firstOrFail();
        $supplierB = Supplier::where('id', '!=', $supplierA->id)->orderBy('id')->firstOrFail();

        // 1. Stock for the Milan recipe (two suppliers, different lot costs).
        $this->receive($supplierA, [1 => [80, 35], 4 => [40, 60], 7 => [60, 15], 11 => [150, 5]]);
        $this->receive($supplierB, [7 => [60, 17], 10 => [40, 12]]);

        // 2. Purchasing: price variance (PO 20, received at 22) and a late, partially received PO.
        $orders = app(PurchaseOrderService::class);
        $receiving = app(PurchaseReceivingService::class);
        $variancePo = $orders->createOrder([
            'supplier_id' => $supplierB->id, 'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->subDays(8)->toDateString(), 'expected_delivery_date' => now()->subDays(1)->toDateString(),
            'lines' => [['material_id' => 10, 'ordered_quantity' => 100, 'unit_price' => 20]],
        ], $this->manager);
        $orders->approveOrder($variancePo, $this->manager);
        $orders->markSent($variancePo->fresh(), $this->manager);
        $variancePo = $variancePo->fresh('lines');
        $receipt = $receiving->createDraftReceiptFromPO($variancePo, $this->manager, ['receipt_date' => now()->subDays(2)->toDateString(), 'lines' => [$variancePo->lines->first()->id => ['quantity_received' => 100, 'unit_cost_purchase' => 22]]]);
        $receiving->postLinkedReceipt($receipt, $this->manager);

        $latePo = $orders->createOrder([
            'supplier_id' => $supplierA->id, 'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->subDays(20)->toDateString(), 'expected_delivery_date' => now()->subDays(6)->toDateString(),
            'lines' => [['material_id' => 11, 'ordered_quantity' => 100, 'unit_price' => 5, 'fabric_color_code' => '1']],
        ], $this->manager);
        $orders->approveOrder($latePo, $this->manager);
        $orders->markSent($latePo->fresh(), $this->manager);
        $latePo = $latePo->fresh('lines');
        $partial = $receiving->createDraftReceiptFromPO($latePo, $this->manager, ['lines' => [$latePo->lines->first()->id => ['quantity_received' => 40, 'unit_cost_purchase' => 5]]]);
        $receiving->postLinkedReceipt($partial, $this->manager);

        $milan = ProductConfiguration::findOrFail(1);

        // 3. Profitable completed order (2 beds) with waste, a usable return, finished goods waiting and an overdue balance.
        $profit = $this->orderLine(self::MARKER, $milan, 2, 2400);
        $profitPo = $this->release($profit, 2);
        $issue = $this->issueRequirements($profitPo);
        $this->recordWaste($profitPo, 7, 1);
        $this->usableReturn($issue, 4, 1);
        $this->completeProduction($profitPo);
        $this->finishedGoods($profitPo, 2);
        $profit->customerOrder->update(['payment_terms_type' => 'CREDIT', 'payment_due_date' => now()->subDays(45)->toDateString()]);

        // 4. Negative-contribution completed order (price below material cost) with rework and extra material.
        $loss = $this->orderLine('ORD-QA14-LOSS', $milan, 1, 150);
        $lossPo = $this->release($loss, 1);
        $this->issueRequirements($lossPo);
        $this->rework($lossPo);
        $this->completeProduction($lossPo);

        // 5. Partially produced order (provisional cost).
        $partialLine = $this->orderLine('ORD-QA14-PARTIAL', $milan, 3, 2200);
        $partialPo = $this->release($partialLine, 3);
        $this->issueRequirements($partialPo);
        $firstOp = $partialPo->operations()->orderBy('sequence_number')->first();
        app(ProductionOrderService::class)->recordProgress($firstOp, 1, 'PROGRESS', 'تقدم جزئي', $this->manager);

        // 6. Split production: one line of 5 released as 2 + 3; the first part completed.
        $split = $this->orderLine('ORD-QA14-SPLIT', $milan, 5, 2300);
        $splitA = $this->release($split, 2);
        $this->issueRequirements($splitA);
        $this->completeProduction($splitA);
        $this->release($split, 3);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $items  material_id => [quantity, unit cost]
     */
    private function receive(Supplier $supplier, array $items): void
    {
        $inventory = app(InventoryService::class);
        $receipt = $inventory->createReceipt([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->subDays(10)->toDateString(),
            'supplier_invoice_number' => 'INV-QA14-'.$supplier->id,
            'items' => collect($items)->map(fn ($values, $materialId) => [
                'material_id' => $materialId,
                'quantity' => $values[0],
                'unit_id' => Material::findOrFail($materialId)->base_unit_id,
                'unit_cost' => $values[1],
                'fabric_color_code' => $this->isFabric($materialId) ? '1' : null,
            ])->values()->all(),
        ], $this->manager);
        $inventory->postReceipt($receipt, $this->manager);
    }

    private function isFabric(int $materialId): bool
    {
        return strtoupper((string) Material::with('category')->findOrFail($materialId)->category?->code) === 'FABRIC';
    }

    private function orderLine(string $number, ProductConfiguration $configuration, int $quantity, float $unitPrice): CustomerOrderLine
    {
        $order = CustomerOrder::create([
            'order_number' => $number,
            'customer_id' => Customer::where('customer_code', 'CUST-QA16-01')->value('id') ?? Customer::orderBy('id')->value('id'),
            'sales_channel_id' => SalesChannel::orderBy('id')->value('id'),
            'created_by_user_id' => $this->manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now()->subDays(12)->toDateString(),
            'priority' => 'NORMAL',
            'payment_terms_type' => 'CASH_ON_DELIVERY',
            'subtotal' => $unitPrice * $quantity,
            'total_amount' => $unitPrice * $quantity,
            'production_approved_by_user_id' => $this->manager->id,
            'production_approved_at' => now()->subDays(11),
        ]);

        return CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'product_model_id' => $configuration->product_model_id,
            'product_configuration_id' => $configuration->id,
            'requested_width_cm' => $configuration->width_cm,
            'requested_length_cm' => $configuration->length_cm,
            'reference_width_cm' => $configuration->width_cm,
            'reference_length_cm' => $configuration->length_cm,
            'has_storage' => (bool) $configuration->has_storage,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
        ]);
    }

    private function release(CustomerOrderLine $line, int $quantity): ProductionOrder
    {
        $service = app(ProductionOrderService::class);
        $po = $service->createFromOrderLine($line, ['released_quantity' => $quantity, 'manufacturing_recipe_version_id' => $line->recipe_version?->id]);
        $service->releaseProductionOrder($po, ProductionRouting::orderBy('id')->firstOrFail(), $this->manager);
        // Same as opening the material-request form in the app: planned requirements come from the approved recipe.
        app(ProductionMaterialRequestService::class)->createMaterialRequirementsFromRecipe($po->fresh());

        return $po->fresh(['operations', 'materialRequirements']);
    }

    /**
     * Requests and issues every planned requirement (FIFO across lots).
     *
     * @param  array<int, float>|null  $only  material_id => quantity (for additional/rework requests)
     */
    private function issueRequirements(ProductionOrder $po, string $reason = 'PLANNED_PRODUCTION', ?array $only = null): MaterialIssue
    {
        $requests = app(ProductionMaterialRequestService::class);
        $lines = $po->materialRequirements
            ->whereNotNull('material_id')
            ->when($only, fn ($reqs) => $reqs->whereIn('material_id', array_keys($only)))
            ->map(fn ($req) => [
                'material_id' => $req->material_id,
                'requested_quantity' => $only[$req->material_id] ?? (float) $req->total_planned_quantity,
                'base_unit_id' => Material::findOrFail($req->material_id)->base_unit_id,
                'request_reason' => $reason,
            ])->values()->all();

        $request = $requests->createRequest($po, $this->manager, ['warehouse_id' => $this->warehouse->id, 'lines' => $lines]);
        $requests->submitRequest($request);
        $request = ProductionMaterialRequest::with('lines')->findOrFail($request->id);

        $fulfillments = [];
        foreach ($request->lines as $line) {
            $needed = (float) $line->approved_quantity;
            $lots = InventoryLot::where('material_id', $line->material_id)->where('warehouse_id', $this->warehouse->id)
                ->where('status', 'ACTIVE')->where('remaining_quantity', '>', 0)->orderBy('received_date')->orderBy('id')->get();
            foreach ($lots as $lot) {
                if ($needed <= 0) {
                    break;
                }
                $take = min($needed, (float) $lot->remaining_quantity);
                $fulfillments[] = ['request_line_id' => $line->id, 'inventory_lot_id' => $lot->id, 'quantity' => $take];
                $needed -= $take;
            }
        }

        return $requests->fulfillRequest($request, $this->manager, $fulfillments);
    }

    private function recordWaste(ProductionOrder $po, int $materialId, float $quantity): void
    {
        $lot = InventoryLot::where('material_id', $materialId)->orderByDesc('id')->firstOrFail();
        ProductionWasteRecord::create([
            'waste_number' => 'WST-QA14-'.$po->id,
            'production_order_id' => $po->id,
            'inventory_lot_id' => $lot->id,
            'material_id' => $materialId,
            'quantity' => $quantity,
            'unit_id' => $lot->base_unit_id,
            'unit_cost' => $lot->unit_cost,
            'total_cost' => round($quantity * (float) $lot->unit_cost, 4),
            'waste_reason_id' => ProductionWasteReason::orderBy('id')->value('id'),
            'detected_department_id' => Department::where('code', 'UPHOLSTERY')->value('id') ?? Department::orderBy('id')->value('id'),
            'responsible_department_id' => Department::where('code', 'CARPENTRY')->value('id'),
            'recorded_by_user_id' => $this->manager->id,
            'notes' => 'قص خاطئ للوح جوانب (بيانات اختبار التقارير)',
            'occurred_at' => now()->subDays(3),
        ]);
    }

    private function usableReturn(MaterialIssue $issue, int $materialId, float $quantity): void
    {
        $inventory = app(InventoryService::class);
        $issueLine = $issue->lines()->where('material_id', $materialId)->firstOrFail();
        $return = $inventory->createReturn([
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->subDays(2)->toDateString(),
            'notes' => 'إرجاع لوح إسفنج سليم غير مستخدم',
            'items' => [['original_issue_line_id' => $issueLine->id, 'returned_quantity' => $quantity]],
        ], $this->manager->id);
        $inventory->postReturn($return, $this->manager);
    }

    private function rework(ProductionOrder $po): void
    {
        $operation = $po->operations()->orderBy('sequence_number')->first();
        $carpentry = Department::where('code', 'CARPENTRY')->value('id') ?? Department::orderBy('id')->value('id');
        $incident = QualityIncident::create([
            'incident_number' => 'QI-QA14-'.$po->id,
            'production_order_id' => $po->id,
            'production_order_operation_id' => $operation->id,
            'affected_quantity' => 1,
            'detected_department_id' => Department::where('code', 'UPHOLSTERY')->value('id') ?? $carpentry,
            'responsible_department_id' => $carpentry,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'انحراف في تجميع الهيكل يتطلب إعادة تصنيع اللوح الجانبي',
            'severity' => 'HIGH',
            'disposition' => 'REWORK',
            'status' => 'ACTION_REQUIRED',
            'detected_by_user_id' => $this->manager->id,
        ]);
        ProductionReworkAction::create([
            'rework_number' => 'RWK-QA14-'.$po->id,
            'quality_incident_id' => $incident->id,
            'production_order_id' => $po->id,
            'source_operation_id' => $operation->id,
            'target_operation_id' => $operation->id,
            'action_type' => 'REWORK',
            'quantity' => 1,
            'status' => 'IN_PROGRESS',
            'assigned_department_id' => $carpentry,
            'authorized_by_user_id' => $this->manager->id,
        ]);
        $this->issueRequirements($po->fresh(['materialRequirements']), 'REWORK', [7 => 2]);
    }

    private function completeProduction(ProductionOrder $po): void
    {
        $service = app(ProductionOrderService::class);
        for ($pass = 0; $pass < 10; $pass++) {
            $open = ProductionOrderOperation::where('production_order_id', $po->id)->whereNotIn('status', ['COMPLETED', 'SKIPPED'])->orderBy('sequence_number')->get();
            if ($open->isEmpty()) {
                return;
            }
            foreach ($open as $operation) {
                $eligible = $service->calculateMaxEligibleQuantity($operation) - $operation->completed_quantity;
                $remaining = $operation->required_quantity - $operation->completed_quantity;
                $quantity = min($eligible, $remaining);
                if ($quantity > 0) {
                    $service->recordProgress($operation->fresh(), $quantity, 'PROGRESS', null, $this->manager);
                }
            }
        }
    }

    private function finishedGoods(ProductionOrder $po, float $quantity): void
    {
        $service = app(FinishedGoodsService::class);
        $receipt = $service->createReceipt($po->fresh(), $this->manager, [
            'warehouse_id' => (Warehouse::where('code', 'FINISHED_GOODS')->first() ?? $this->warehouse)->id,
            'received_quantity' => $quantity,
            'receipt_date' => now()->subDay()->toDateString(),
        ]);
        $service->postReceipt($receipt, $this->manager);
    }
}
