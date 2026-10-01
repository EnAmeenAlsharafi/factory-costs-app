<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\Department;
use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductionRouting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerPaymentService;
use App\Services\DeliveryOrderService;
use App\Services\FinishedGoodsService;
use App\Services\InventoryService;
use App\Services\ProductionMaterialRequestService;
use App\Services\ProductionOrderService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Stage 16 mobile QA dataset (local development only — never registered in DatabaseSeeder).
 *
 * Builds, through the real services, the records every mobile journey needs:
 * worker tasks, a submitted material request with multiple fabric lots, a draft receipt,
 * delivery tasks in each field state, purchasing documents and a pending customer payment.
 *
 * Run: php artisan db:seed --class=MobileQaSeeder
 */
class MobileQaSeeder extends Seeder
{
    private const MARKER_ORDER = 'ORD-QA16-PROD';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('MobileQaSeeder runs only in the local environment.');

            return;
        }

        if (CustomerOrder::where('order_number', self::MARKER_ORDER)->exists()) {
            $this->command?->info('Mobile QA data already present.');
        } else {
            DB::transaction(fn () => $this->seedDataset());
            $this->command?->info('Mobile QA data created.');
        }

        DB::transaction(fn () => $this->ensureReleasableDraftOrder());
    }

    /**
     * A DRAFT production order with an approved recipe, so the manager "approve → release" journey can be exercised.
     */
    private function ensureReleasableDraftOrder(): void
    {
        if (CustomerOrder::where('order_number', 'ORD-QA16-REL')->exists()) {
            return;
        }

        $manager = User::where('username', 'prod.manager')->firstOrFail();
        $configuration = ProductConfiguration::with('productModel')->findOrFail(175);
        $fabric = Material::findOrFail(14);
        $fabricSupplier = $fabric->suppliers()->first() ?? Supplier::orderBy('id')->firstOrFail();
        $color = FabricColor::where('material_id', $fabric->id)->where('color_code', '204')->first();

        $line = $this->approvedOrderLine('ORD-QA16-REL', $this->qaCustomer(), SalesChannel::orderBy('id')->firstOrFail(), $manager, $configuration, $fabric, $fabricSupplier, $color, 2, 'CASH_ON_DELIVERY');
        app(ProductionOrderService::class)->createFromOrderLine($line, ['released_quantity' => 2, 'manufacturing_recipe_version_id' => $line->recipe_version?->id]);

        $this->command?->info('Releasable DRAFT production order created (ORD-QA16-REL).');
    }

    private function seedDataset(): void
    {
        $manager = User::where('username', 'prod.manager')->firstOrFail();
        $warehouseKeeper = User::where('username', 'warehouse.keeper')->firstOrFail();
        $driver = User::where('username', 'delivery.driver')->firstOrFail();
        $purchasingUser = $this->qaUser('purchasing.user', 'ماجد - المشتريات', 'purchasing_user');
        $receivablesUser = $this->qaUser('receivables.user', 'نورة - التحصيل', 'receivables_user');

        $rawWarehouse = Warehouse::where('code', '!=', 'FINISHED_GOODS')->orderBy('id')->firstOrFail();
        $finishedWarehouse = Warehouse::where('code', 'FINISHED_GOODS')->first() ?? $rawWarehouse;
        $customer = $this->qaCustomer();
        $salesChannel = SalesChannel::orderBy('id')->firstOrFail();

        $configuration = ProductConfiguration::with('productModel')->findOrFail(175);
        $fabric = Material::findOrFail(14);
        $fabricSupplier = $fabric->suppliers()->first() ?? Supplier::orderBy('id')->firstOrFail();
        $color = FabricColor::where('material_id', $fabric->id)->where('color_code', '204')->first();

        // 1. Production: an approved order released to the shop floor (worker tasks).
        $productionLine = $this->approvedOrderLine(self::MARKER_ORDER, $customer, $salesChannel, $manager, $configuration, $fabric, $fabricSupplier, $color, 3, 'FULL_BEFORE_PRODUCTION');
        $productionOrders = app(ProductionOrderService::class);
        $po = $productionOrders->createFromOrderLine($productionLine, ['released_quantity' => 3, 'manufacturing_recipe_version_id' => $productionLine->recipe_version?->id]);
        $po->update(['planned_completion_date' => now()->addDays(5), 'production_notes' => 'تثبيت السحارة بمفصلات مزدوجة.']);
        $productionOrders->releaseProductionOrder($po, ProductionRouting::orderBy('id')->firstOrFail(), $manager);

        // 2. Warehouse: a second fabric lot in colour 204, then a submitted material request needing both lots.
        $inventory = app(InventoryService::class);
        $extraLotReceipt = $inventory->createReceipt([
            'supplier_id' => $fabricSupplier->id,
            'warehouse_id' => $rawWarehouse->id,
            'receipt_date' => now()->subDay()->toDateString(),
            'supplier_invoice_number' => 'INV-QA16-01',
            'items' => [[
                'material_id' => $fabric->id,
                'fabric_color_code' => '204',
                'quantity' => 12,
                'unit_id' => $fabric->base_unit_id,
                'unit_cost' => 38,
            ]],
        ], $warehouseKeeper);
        $inventory->postReceipt($extraLotReceipt, $warehouseKeeper);

        $materialRequests = app(ProductionMaterialRequestService::class);
        $materialRequest = $materialRequests->createRequest($po, $manager, [
            'warehouse_id' => $rawWarehouse->id,
            'notes' => 'طلب خامات لتنجيد 3 أسرة — لون 204',
            'lines' => [
                ['material_id' => $fabric->id, 'fabric_color_id' => $color?->id, 'fabric_color_code' => '204', 'requested_quantity' => 30, 'approved_quantity' => 30, 'base_unit_id' => $fabric->base_unit_id],
                ['material_id' => 1, 'requested_quantity' => 6, 'approved_quantity' => 6, 'base_unit_id' => Material::findOrFail(1)->base_unit_id],
            ],
        ]);
        $materialRequests->submitRequest($materialRequest);

        // A draft receipt waiting to be posted.
        $inventory->createReceipt([
            'supplier_id' => $fabricSupplier->id,
            'warehouse_id' => $rawWarehouse->id,
            'receipt_date' => now()->toDateString(),
            'supplier_invoice_number' => 'INV-QA16-02',
            'items' => [[
                'material_id' => $fabric->id,
                'fabric_color_code' => '5',
                'quantity' => 8,
                'unit_id' => $fabric->base_unit_id,
                'unit_cost' => 36,
            ]],
        ], $warehouseKeeper);

        // 3. Delivery: finished goods for a COD order and tasks in each field state.
        $deliveryLine = $this->approvedOrderLine('ORD-QA16-DLV', $customer, $salesChannel, $manager, $configuration, $fabric, $fabricSupplier, $color, 3, 'CASH_ON_DELIVERY');
        $deliveryPo = $productionOrders->createFromOrderLine($deliveryLine, ['released_quantity' => 3, 'manufacturing_recipe_version_id' => $deliveryLine->recipe_version?->id]);
        $deliveryPo->update(['status' => 'COMPLETED', 'completed_quantity' => 3, 'completed_at' => now()]);
        $finishedGoods = app(FinishedGoodsService::class);
        $fgReceipt = $finishedGoods->createReceipt($deliveryPo, $manager, [
            'warehouse_id' => $finishedWarehouse->id,
            'received_quantity' => 3,
            'receipt_date' => now()->toDateString(),
        ]);
        $finishedGoods->postReceipt($fgReceipt, $manager);

        $deliveries = app(DeliveryOrderService::class);
        $makeDelivery = function (string $timeNote) use ($deliveries, $deliveryLine, $deliveryPo, $manager, $driver, $customer) {
            $delivery = $deliveries->createDeliveryOrder($deliveryLine->customerOrder, $manager, [
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'city_snapshot' => 'الرياض',
                'district_snapshot' => 'حي النرجس',
                'delivery_address_snapshot' => 'شارع الأمير سعود بن عبدالله، فيلا 14، بجوار مسجد النرجس',
                'location_notes' => 'الموقع: https://maps.google.com/?q=24.8408,46.6566',
                'scheduled_delivery_date' => now()->toDateString(),
                'scheduled_time_notes' => $timeNote,
                'installation_required' => true,
                'lines' => [['production_order_id' => $deliveryPo->id, 'quantity' => 1]],
            ]);
            $deliveries->markReady($delivery, $manager);

            return $deliveries->assignDriver($delivery->fresh(), $driver, $manager);
        };

        $makeDelivery('صباحاً 9-12');
        $outForDelivery = $makeDelivery('ظهراً 12-3');
        $deliveries->dispatchDelivery($outForDelivery, $driver);
        $awaitingInstall = $makeDelivery('عصراً 3-6');
        $deliveries->dispatchDelivery($awaitingInstall, $driver);
        $deliveries->markDelivered($awaitingInstall->fresh(), $driver);

        // 4. Purchasing: a submitted shortage request and a sent, overdue purchase order.
        $purchaseRequests = app(PurchaseRequestService::class);
        $purchaseRequest = $purchaseRequests->createRequest([
            'warehouse_id' => $rawWarehouse->id,
            'source_type' => 'PRODUCTION_SHORTAGE',
            'source_reference_id' => $po->id,
            'priority' => 'URGENT',
            'required_by_date' => now()->addDays(3)->toDateString(),
            'justification' => 'نقص قماش بوكلية لون 204 لأمر إنتاج QA',
            'lines' => [[
                'material_id' => $fabric->id,
                'fabric_color_code' => '204',
                'requested_quantity' => 40,
                'estimated_unit_cost' => 38,
                'preferred_supplier_id' => $fabricSupplier->id,
            ]],
        ], $purchasingUser);
        $purchaseRequests->submitRequest($purchaseRequest, $purchasingUser);

        $purchaseOrders = app(PurchaseOrderService::class);
        $purchaseOrder = $purchaseOrders->createOrder([
            'supplier_id' => $fabricSupplier->id,
            'warehouse_id' => $rawWarehouse->id,
            'order_date' => now()->subDays(10)->toDateString(),
            'expected_delivery_date' => now()->subDays(2)->toDateString(),
            'lines' => [[
                'material_id' => $fabric->id,
                'fabric_color_code' => '5',
                'ordered_quantity' => 20,
                'unit_price' => 36,
            ]],
        ], $purchasingUser);
        $purchaseOrders->approveOrder($purchaseOrder, $manager);
        $purchaseOrders->markSent($purchaseOrder->fresh(), $purchasingUser);

        // 5. Receivables: a customer payment waiting for confirmation.
        app(CustomerPaymentService::class)->createPayment([
            'customer_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1500,
            'payment_method' => 'BANK_TRANSFER',
            'reference_number' => 'TRX-QA16-001',
            'notes' => 'دفعة مقدمة لطلب QA',
        ], $receivablesUser);
    }

    private function qaUser(string $username, string $name, string $roleName): User
    {
        return User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $username.'@sadir-factory.com',
                'password' => Hash::make('password'),
                'role_id' => Role::where('name', $roleName)->firstOrFail()->id,
                'department_id' => $roleName === 'purchasing_user' ? Department::where('code', 'WAREHOUSE')->value('id') : null,
                'is_active' => true,
            ],
        );
    }

    private function qaCustomer(): Customer
    {
        return Customer::firstOrCreate(
            ['customer_code' => 'CUST-QA16-01'],
            [
                'name' => 'عبدالرحمن القحطاني',
                'customer_type_id' => CustomerType::orderBy('id')->firstOrFail()->id,
                'mobile' => '0501234567',
                'phone' => '0501234567',
                'city' => 'الرياض',
                'address' => 'حي النرجس، شارع الأمير سعود بن عبدالله، فيلا 14',
                'is_active' => true,
            ],
        );
    }

    private function approvedOrderLine(string $orderNumber, Customer $customer, SalesChannel $salesChannel, User $manager, ProductConfiguration $configuration, Material $fabric, Supplier $fabricSupplier, ?FabricColor $color, int $quantity, string $paymentTerms): CustomerOrderLine
    {
        $unitPrice = 1450;
        $order = CustomerOrder::create([
            'order_number' => $orderNumber,
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $manager->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now()->subDays(3),
            'requested_delivery_date' => now()->addDays(7),
            'priority' => 'URGENT',
            'payment_terms_type' => $paymentTerms,
            'subtotal' => $unitPrice * $quantity,
            'total_amount' => $unitPrice * $quantity,
            'production_approved_by_user_id' => $manager->id,
            'production_approved_at' => now()->subDays(2),
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
            'fabric_supplier_id' => $fabricSupplier->id,
            'fabric_material_id' => $fabric->id,
            'fabric_color_id' => $color?->id,
            'fabric_color_code' => '204',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
        ]);
    }
}
