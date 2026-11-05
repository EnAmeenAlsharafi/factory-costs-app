<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\SalesImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncompleteImportedOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $csAgent;

    protected CustomerOrder $incompleteOrder;

    protected CustomerOrderLine $incompleteLine;

    protected ProductModel $productModel;

    protected ProductConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->csAgent = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id, 'is_active' => true]);

        $customerType = CustomerType::firstOrCreate(['code' => 'SADIR_STORE'], ['name_ar' => 'متجر سدير', 'is_active' => true]);
        $customer = Customer::create([
            'customer_code' => 'CUST-INC-001',
            'name' => 'عميل اختبار الاستكمال',
            'customer_type_id' => $customerType->id,
            'mobile' => '0591234567',
            'is_active' => true,
        ]);

        $channel = SalesChannel::where('code', 'SADIR_STORE')->first();

        $batch = SalesImportBatch::create([
            'file_name' => 'test.csv',
            'file_hash' => 'hash-incomplete-test',
            'sales_channel_id' => $channel->id,
            'uploaded_by_user_id' => $this->csAgent->id,
            'status' => 'CONFIRMED',
        ]);

        $this->incompleteOrder = CustomerOrder::create([
            'order_number' => 'ORD-INC-0001',
            'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id,
            'import_batch_id' => $batch->id,
            'created_by_user_id' => $this->csAgent->id,
            'status' => 'DRAFT',
            'order_date' => now(),
            'total_amount' => 700,
        ]);

        $this->incompleteLine = CustomerOrderLine::create([
            'customer_order_id' => $this->incompleteOrder->id,
            'line_type' => 'MANUFACTURED',
            'external_product_name' => 'سرير اختبار غير معروف',
            'is_incomplete' => true,
            'quantity' => 1,
            'unit_price' => 700,
            'line_total' => 700,
        ]);

        $this->productModel = ProductModel::create(['model_code' => 'MOD-INC-001', 'name_ar' => 'موديل اختبار الاستكمال', 'is_active' => true]);
        $this->configuration = ProductConfiguration::create([
            'configuration_code' => 'CFG-INC-001',
            'product_model_id' => $this->productModel->id,
            'width_cm' => 160,
            'length_cm' => 200,
            'has_storage' => false,
            'is_active' => true,
        ]);
    }

    public function test_queue_lists_only_draft_orders_with_an_incomplete_line(): void
    {
        $completeOrder = CustomerOrder::create([
            'order_number' => 'ORD-COMPLETE-0001',
            'customer_id' => $this->incompleteOrder->customer_id,
            'sales_channel_id' => $this->incompleteOrder->sales_channel_id,
            'created_by_user_id' => $this->csAgent->id,
            'status' => 'DRAFT',
            'order_date' => now(),
            'total_amount' => 500,
        ]);
        CustomerOrderLine::create([
            'customer_order_id' => $completeOrder->id,
            'line_type' => 'MANUFACTURED',
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'is_incomplete' => false,
            'quantity' => 1,
            'unit_price' => 500,
            'line_total' => 500,
        ]);

        $response = $this->actingAs($this->csAgent)->get(route('sales.incomplete-imports.index'));

        $response->assertOk();
        $response->assertSee('ORD-INC-0001');
        $response->assertDontSee('ORD-COMPLETE-0001');
    }

    public function test_unauthorized_user_cannot_view_the_queue(): void
    {
        $worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'is_active' => true]);

        $this->actingAs($worker)->get(route('sales.incomplete-imports.index'))->assertForbidden();
    }

    public function test_completing_the_order_via_the_normal_edit_form_clears_is_incomplete_and_keeps_status_draft(): void
    {
        $response = $this->actingAs($this->csAgent)->put(route('sales.orders.update', $this->incompleteOrder), [
            'customer_id' => $this->incompleteOrder->customer_id,
            'sales_channel_id' => $this->incompleteOrder->sales_channel_id,
            'order_date' => now()->format('Y-m-d'),
            'priority' => 'NORMAL',
            'lines' => [
                [
                    'id' => $this->incompleteLine->id,
                    'line_type' => 'MANUFACTURED',
                    'product_model_id' => $this->productModel->id,
                    'product_configuration_id' => $this->configuration->id,
                    'requested_width_cm' => 160,
                    'requested_length_cm' => 200,
                    'quantity' => 1,
                    'unit_price' => 700,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->incompleteLine->refresh();
        $this->incompleteOrder->refresh();
        $this->assertFalse((bool) $this->incompleteLine->is_incomplete);
        $this->assertEquals('DRAFT', $this->incompleteOrder->status);

        $this->actingAs($this->csAgent)->get(route('sales.incomplete-imports.index'))
            ->assertDontSee('ORD-INC-0001');
    }

    public function test_submitting_the_completed_order_for_review_moves_it_to_pending_production_review(): void
    {
        $this->incompleteLine->update(['is_incomplete' => false, 'requested_width_cm' => 160, 'requested_length_cm' => 200, 'product_model_id' => $this->productModel->id]);

        $response = $this->actingAs($this->csAgent)->post(route('sales.orders.submit-review', $this->incompleteOrder));

        $response->assertRedirect();
        $this->assertEquals('PENDING_PRODUCTION_REVIEW', $this->incompleteOrder->fresh()->status);
    }
}
