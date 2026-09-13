<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishedGoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Warehouse $warehouse;

    protected ProductionOrder $productionOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $role = Role::where('name', 'production_manager')->first();
        $this->user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->warehouse = Warehouse::create([
            'code' => 'FINISHED_GOODS',
            'name_ar' => 'مخزن المنتجات الجاهزة الرئيسي',
            'is_active' => true,
        ]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'RETAIL'],
            ['name_ar' => 'عملاء القطاعي', 'is_active' => true]
        );

        $customer = Customer::create([
            'customer_code' => 'CUST-FG-001',
            'name' => 'عميل تجريبي للجاهز',
            'customer_type_id' => $customerType->id,
            'phone' => '0500000001',
            'is_active' => true,
        ]);

        $productModel = ProductModel::create([
            'model_code' => 'FG-BED-001',
            'name_ar' => 'سرير كينج مخمل',
            'category' => 'BED',
            'is_active' => true,
        ]);

        $salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'DIRECT'],
            ['name_ar' => 'مبيعات مباشرة', 'is_active' => true]
        );

        $customerOrder = CustomerOrder::create([
            'order_number' => 'SO-2026-9001',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'created_by_user_id' => $this->user->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 5000,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $customerOrder->id,
            'product_model_id' => $productModel->id,
            'requested_width_cm' => 200,
            'requested_length_cm' => 200,
            'requested_height_cm' => 50,
            'quantity' => 10,
            'unit_price' => 500,
            'total_price' => 5000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PO-2026-9001',
            'customer_order_id' => $customerOrder->id,
            'customer_order_line_id' => $orderLine->id,
            'status' => 'COMPLETED',
            'planned_quantity' => 10,
            'released_quantity' => 10,
            'completed_quantity' => 8,
        ]);
    }

    public function test_can_create_and_post_finished_goods_receipt(): void
    {
        $response = $this->actingAs($this->user)->post(route('finished-goods.receipts.store'), [
            'production_order_id' => $this->productionOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'received_quantity' => 5,
            'receipt_date' => now()->format('Y-m-d'),
            'notes' => 'تسليم دفعة أولى 5 أسرة جاهزة',
            'action' => 'post',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('finished_goods_receipts', [
            'production_order_id' => $this->productionOrder->id,
            'quantity' => 5,
            'status' => 'POSTED',
        ]);

        $this->assertDatabaseHas('finished_goods_movements', [
            'production_order_id' => $this->productionOrder->id,
            'movement_type' => 'PRODUCTION_RECEIPT',
            'direction' => 'IN',
            'quantity' => 5,
        ]);
    }

    public function test_cannot_exceed_completed_quantity_ceiling(): void
    {
        // Completed quantity is 8. Attempting to receive 10 should fail validation/service constraint.
        $response = $this->actingAs($this->user)->post(route('finished-goods.receipts.store'), [
            'production_order_id' => $this->productionOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'received_quantity' => 10,
            'receipt_date' => now()->format('Y-m-d'),
            'action' => 'post',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('finished_goods_receipts', [
            'production_order_id' => $this->productionOrder->id,
            'received_quantity' => 10,
        ]);
    }
}
