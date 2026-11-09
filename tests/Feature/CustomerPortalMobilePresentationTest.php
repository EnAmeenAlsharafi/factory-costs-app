<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPortalRequest;
use App\Models\DeliveryOrder;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductModel;
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

    public function test_request_card_nests_a_product_card_with_each_line_image(): void
    {
        $user = $this->client();
        $model = ProductModel::factory()->create(['reference_image_path' => 'models/milan.jpg']);
        $ready = $this->readyProduct();
        $ready->update(['sales_image_url' => 'https://cdn.example.test/ready.jpg']);
        $withoutImage = ProductModel::factory()->create(['reference_image_path' => null]);
        $submission = $this->request($user, [
            ['line_type' => 'MANUFACTURED', 'product_model_id' => $model->id, 'product_name' => 'سرير ميلان', 'requested_width_cm' => 90, 'requested_length_cm' => 190, 'has_storage' => 0, 'quantity' => 2],
            ['line_type' => 'READY_STOCK', 'material_id' => $ready->id, 'product_name' => 'مرتبة جاهزة', 'quantity' => 1],
            ['line_type' => 'MANUFACTURED', 'product_model_id' => $withoutImage->id, 'product_name' => 'كنبة بلا صورة', 'requested_width_cm' => 100, 'requested_length_cm' => 200, 'quantity' => 1],
        ]);

        foreach (['portal.home', 'portal.requests.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertSee('order-line-card', false)
                ->assertDontSee('has-mobile-cards-lg', false)
                ->assertSee($model->image_url, false)
                ->assertSee('https://cdn.example.test/ready.jpg', false)
                ->assertSee('سرير ميلان')->assertSee('90 × 190')->assertSee('كنبة بلا صورة')
                ->assertSee('fa-couch', false)
                ->assertDontSee('عرض كل المنتجات');
        }
        $this->get(route('portal.requests.show', $submission))->assertOk()->assertSee($model->image_url, false);
    }

    public function test_long_request_card_limits_nested_products_and_links_to_the_full_list(): void
    {
        $user = $this->client();
        $model = ProductModel::factory()->create();
        $lines = collect(range(1, 5))->map(fn (int $number): array => [
            'line_type' => 'MANUFACTURED', 'product_model_id' => $model->id, 'product_name' => "منتج رقم {$number}",
            'requested_width_cm' => 90, 'requested_length_cm' => 190, 'quantity' => 1,
        ])->all();
        $this->request($user, $lines);

        $this->actingAs($user)->get(route('portal.requests.index'))->assertOk()
            ->assertSee('منتج رقم 3')->assertDontSee('منتج رقم 4')
            ->assertSee('عرض كل المنتجات (5)');
    }

    public function test_order_card_and_details_show_product_images_and_a_customer_design_stays_on_its_own_request(): void
    {
        $user = $this->client();
        $model = ProductModel::factory()->create(['reference_image_path' => 'models/oslo.jpg']);
        $order = CustomerOrder::factory()->create(['customer_id' => $user->customer_id, 'status' => 'APPROVED']);
        $submission = $this->request($user, [[
            'line_key' => '5f6f1f3e-7d9e-4f3a-9d0a-0d6a2f1b7c11', 'line_type' => 'MANUFACTURED', 'custom_design' => true,
            'custom_design_image_path' => 'customer-order-designs/client.png', 'product_name' => 'تصميم العميل',
            'requested_width_cm' => 160, 'requested_length_cm' => 200, 'quantity' => 1,
        ]], ['customer_order_id' => $order->id, 'status' => 'CONFIRMED']);
        CustomerOrderLine::factory()->create(['customer_order_id' => $order->id, 'product_model_id' => $model->id]);
        CustomerOrderLine::factory()->create([
            'customer_order_id' => $order->id, 'custom_design' => true, 'custom_design_name' => 'تصميم العميل',
            'custom_design_image_path' => 'customer-order-designs/client.png',
        ]);
        $designUrl = route('portal.requests.design-image', [$submission, '5f6f1f3e-7d9e-4f3a-9d0a-0d6a2f1b7c11']);

        foreach ([route('portal.orders.index'), route('portal.orders.show', $order)] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee($model->image_url, false)
                ->assertSee($designUrl, false);
        }
    }

    public function test_order_form_receives_product_images_and_hides_storage_only_for_customer_factory_models(): void
    {
        $user = $this->client();
        $model = ProductModel::factory()->create(['is_active' => true, 'reference_image_path' => 'models/milan.jpg']);
        $ready = $this->readyProduct();
        $ready->update(['sales_image_url' => 'https://cdn.example.test/ready.jpg', 'has_ready_options' => false]);

        $this->actingAs($user)->get(route('portal.requests.create'))->assertOk()
            ->assertViewHas('models', fn ($models): bool => $models->firstWhere('id', $model->id)['image_url'] === $model->image_url)
            ->assertViewHas('readyProducts', fn ($products): bool => $products->firstWhere('id', $ready->id)['image_url'] === 'https://cdn.example.test/ready.jpg')
            ->assertSee('imageFor(line, previewUrl)', false)
            ->assertSee("line.line_type === 'MANUFACTURED' && line.custom_design\"", false);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $attributes
     */
    protected function request(User $user, array $lines, array $attributes = []): CustomerPortalRequest
    {
        return CustomerPortalRequest::create(array_merge([
            'customer_id' => $user->customer_id, 'created_by_user_id' => $user->id,
            'status' => 'DRAFT', 'revision' => 1, 'lines' => $lines,
        ], $attributes));
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
