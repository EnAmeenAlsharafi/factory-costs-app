<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPortalRequest;
use App\Models\DeliveryOrder;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CustomerPortalAccountService;
use App\Services\StatusPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the presentation contract the mobile portal views depend on: the machine-readable
 * fulfilment `state`, the delivered ratio that drives the progress meter, and the single
 * source of truth for customer-facing status labels.
 */
class CustomerPortalMobilePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_progress_reports_a_machine_state_whose_label_comes_from_the_status_presenter(): void
    {
        $order = $this->orderWithQuantity(10);

        $progress = app(CustomerPortalAccountService::class)->progress($order);

        $this->assertSame('APPROVED', $progress['state']);
        $this->assertSame(StatusPresenter::label('portal_order', 'APPROVED'), $progress['status']);
        $this->assertSame('معتمد وبانتظار التجهيز', $progress['status']);
    }

    public function test_progress_tracks_partial_and_full_delivery_states_with_a_ratio_for_the_meter(): void
    {
        $order = $this->orderWithQuantity(10);
        $service = app(CustomerPortalAccountService::class);

        $this->assertSame(0.0, $service->progress($order)['delivered_ratio']);

        $this->deliver($order, 4);
        $partial = $service->progress($order->fresh());
        $this->assertSame('PARTIALLY_DELIVERED', $partial['state']);
        $this->assertSame('تم التسليم جزئيًا', $partial['status']);
        $this->assertSame(0.4, round($partial['delivered_ratio'], 4));

        $this->deliver($order, 6);
        $complete = $service->progress($order->fresh());
        $this->assertSame('DELIVERED', $complete['state']);
        $this->assertSame(1.0, $complete['delivered_ratio']);
    }

    public function test_delivered_ratio_never_exceeds_one_and_is_zero_for_an_order_without_quantity(): void
    {
        $service = app(CustomerPortalAccountService::class);

        $empty = $this->orderWithQuantity(0);
        $this->assertSame(0.0, $service->progress($empty)['delivered_ratio']);

        $overDelivered = $this->orderWithQuantity(5);
        $this->deliver($overDelivered, 8);
        $this->assertSame(1.0, $service->progress($overDelivered->fresh())['delivered_ratio']);
    }

    public function test_cancelled_order_reports_the_cancelled_state_regardless_of_deliveries(): void
    {
        $order = $this->orderWithQuantity(5);
        $this->deliver($order, 5);
        $order->update(['status' => 'CANCELLED']);

        $this->assertSame('CANCELLED', app(CustomerPortalAccountService::class)->progress($order->fresh())['state']);
    }

    public function test_status_presenter_covers_every_portal_request_status_with_a_tone_and_icon(): void
    {
        foreach (array_keys(CustomerPortalRequest::STATUSES) as $status) {
            $presented = StatusPresenter::present('portal_request', $status);

            $this->assertNotSame($status, $presented['label'], "No customer-facing label for {$status}.");
            $this->assertNotSame('fa-circle-info', $presented['icon'], "No icon for {$status}.");
        }
    }

    public function test_portal_screens_render_the_mobile_shell_and_status_chips(): void
    {
        $user = $this->client();
        $order = $this->orderWithQuantity(4, $user->customer);
        $this->deliver($order, 2);

        $this->actingAs($user)->get(route('portal.orders.index'))
            ->assertOk()
            ->assertSee('portal-tabbar', false)
            ->assertSee('status-chip', false)
            ->assertSee('تم التسليم جزئيًا');

        $this->get(route('portal.orders.show', $order))
            ->assertOk()
            ->assertSee('portal-facts', false)
            ->assertSee('تم التسليم جزئيًا');
    }

    protected function client(): User
    {
        $customer = Customer::factory()->create();

        return User::factory()->create([
            'role_id' => Role::where('name', 'customer_portal')->value('id'),
            'customer_id' => $customer->id,
            'is_active' => true,
        ]);
    }

    protected function orderWithQuantity(float $quantity, ?Customer $customer = null): CustomerOrder
    {
        $customer ??= Customer::factory()->create();
        $order = CustomerOrder::factory()->create(['customer_id' => $customer->id, 'status' => 'APPROVED']);

        if ($quantity > 0) {
            CustomerOrderLine::factory()->readyStock()->create([
                'customer_order_id' => $order->id,
                'material_id' => $this->readyProduct()->id,
                'quantity' => $quantity,
            ]);
        }

        return $order->fresh();
    }

    protected function deliver(CustomerOrder $order, float $quantity): void
    {
        $delivery = DeliveryOrder::create([
            'delivery_number' => 'DEL-'.fake()->unique()->numberBetween(100000, 999999),
            'customer_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'customer_name_snapshot' => $order->customer->name,
            'sales_channel_id' => $order->sales_channel_id,
            'status' => 'DELIVERED',
            'delivered_at' => now(),
            'created_by_user_id' => User::where('username', 'admin')->value('id'),
        ]);

        $delivery->lines()->create([
            'line_type' => 'READY_STOCK',
            'material_id' => $this->readyProduct()->id,
            'quantity' => $quantity,
        ]);
    }

    protected function readyProduct(): Material
    {
        return Material::factory()->create([
            'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->value('id'),
            'base_unit_id' => UnitOfMeasure::where('code', 'PIECE')->value('id'),
            'is_sellable_ready_product' => true,
        ]);
    }
}
