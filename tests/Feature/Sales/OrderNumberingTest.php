<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerType;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\CustomerOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected User $creator;

    protected Customer $customer;

    protected SalesChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->creator = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id, 'is_active' => true]);

        $customerType = CustomerType::firstOrCreate(['code' => 'COMMERCIAL'], ['name_ar' => 'عملاء جملة ومشاريع', 'is_active' => true]);
        $this->customer = Customer::create([
            'customer_code' => 'CUST-NUM-001',
            'name' => 'عميل اختبار الترقيم',
            'customer_type_id' => $customerType->id,
            'is_active' => true,
        ]);

        $this->channel = SalesChannel::where('code', 'SADIR_STORE')->first();
    }

    protected function createOrder(array $overrides = []): CustomerOrder
    {
        return app(CustomerOrderService::class)->createOrder(array_merge([
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->channel->id,
        ], $overrides), [
            [
                'line_type' => 'READY_STOCK',
                'material_id' => null,
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ], $this->creator);
    }

    public function test_an_order_with_an_external_reference_uses_it_as_the_order_number(): void
    {
        $order = $this->createOrder(['external_order_reference' => 'SALLA-9001']);

        $this->assertEquals('SALLA-9001', $order->order_number);
        $this->assertEquals('SALLA-9001', $order->external_order_reference);
    }

    public function test_a_manual_order_without_an_external_reference_gets_an_auto_generated_number(): void
    {
        $order = $this->createOrder();

        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->order_number);
    }

    public function test_a_colliding_external_reference_falls_back_to_an_auto_generated_number(): void
    {
        CustomerOrder::factory()->create(['order_number' => 'SALLA-DUPLICATE']);

        $order = $this->createOrder(['external_order_reference' => 'SALLA-DUPLICATE']);

        $this->assertNotEquals('SALLA-DUPLICATE', $order->order_number);
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->order_number);
        $this->assertEquals('SALLA-DUPLICATE', $order->external_order_reference);
    }
}
