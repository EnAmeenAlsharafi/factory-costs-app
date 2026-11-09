<?php

namespace Tests\Feature\Sales;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\User;
use App\Services\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_approved_order_not_yet_released_points_to_release_and_offers_no_delivery(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $order = CustomerOrder::factory()->create(['status' => 'APPROVED_FOR_PRODUCTION']);
        CustomerOrderLine::factory()->create(['customer_order_id' => $order->id, 'quantity' => 2]);

        $this->actingAs($admin)->get(route('sales.orders.show', $order))->assertOk()
            ->assertSee('الخطوة التالية: أطلق أوامر الإنتاج')
            ->assertSee(route('production.pipeline.index', ['stage' => 'release', 'search' => $order->order_number]), false)
            ->assertDontSee(route('delivery.orders.create', ['customer_order_id' => $order->id]), false);

        $this->get(route('delivery.orders.create', ['customer_order_id' => $order->id]))
            ->assertRedirect(route('sales.orders.show', $order))
            ->assertSessionHas('error');
    }

    public function test_ready_stock_lines_never_go_to_production(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $order = CustomerOrder::factory()->create(['status' => 'APPROVED_FOR_PRODUCTION']);
        $readyLine = CustomerOrderLine::factory()->readyStock()->create(['customer_order_id' => $order->id]);

        $this->actingAs($admin)->get(route('production.pipeline.index', ['stage' => 'release']))
            ->assertOk()
            ->assertDontSee($order->order_number);

        $this->expectException(ValidationException::class);
        app(ProductionOrderService::class)->createFromOrderLine($readyLine, ['released_quantity' => 1]);
    }
}
