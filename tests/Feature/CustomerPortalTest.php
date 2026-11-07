<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerPayment;
use App\Models\CustomerPortalRequest;
use App\Models\DeliveryOrder;
use App\Models\FabricColor;
use App\Models\ManufacturingRecipe;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductConfiguration;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CustomerOrderService;
use App\Services\CustomerPortalAccountService;
use App\Services\CustomerPortalService;
use App\Services\PaymentAllocationService;
use App\Services\ProductionOrderService;
use App\Services\ReceivablesReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_login_redirects_to_portal_and_blocks_all_internal_routes(): void
    {
        $user = $this->client();

        $this->post(route('login'), ['username' => $user->username, 'password' => 'password'])->assertRedirect(route('portal.home'));

        foreach (['portal.home', 'portal.requests.index', 'portal.requests.create', 'portal.orders.index', 'portal.account', 'portal.receipts', 'portal.profile'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('dashboard'))->assertRedirect(route('portal.home'));
        foreach (['sales.orders.index', 'users.index', 'reports.index', 'api.search.product-models', 'sales.portal-requests.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_guest_and_unlinked_or_inactive_customer_accounts_cannot_access_portal(): void
    {
        $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
        $user = User::factory()->create(['role_id' => Role::where('name', 'customer_portal')->value('id'), 'is_active' => true]);
        $this->actingAs($user)->get(route('portal.home'))->assertForbidden();
        $client = $this->client();
        $client->customer->update(['is_active' => false]);
        $this->actingAs($client)->get(route('portal.home'))->assertForbidden();
    }

    public function test_staff_session_is_sent_to_the_portal_sign_in_notice_and_cannot_write_to_the_portal(): void
    {
        $this->actingAs($this->admin());

        foreach (['portal.home', 'portal.requests.index', 'portal.orders.index', 'portal.account'] as $route) {
            $this->get(route($route))->assertRedirect(route('portal.login'));
        }
        $this->get(route('portal.login'))->assertOk()->assertSee('يوجد حساب موظف');
        $this->post(route('portal.requests.store'), $this->data())->assertForbidden();
        $this->assertSame(0, CustomerPortalRequest::count());
    }

    public function test_customer_cannot_view_or_mutate_another_customers_request_or_order(): void
    {
        $user = $this->client();
        $other = $this->client();
        $submission = $this->draft($other);
        $order = CustomerOrder::factory()->create(['customer_id' => $other->customer_id]);

        $this->actingAs($user);

        $this->get(route('portal.requests.show', $submission))->assertNotFound();
        $this->get(route('portal.requests.edit', $submission))->assertNotFound();
        $this->put(route('portal.requests.update', $submission), $this->data())->assertNotFound();
        foreach (['submit', 'confirm', 'changes'] as $action) {
            $this->post(route('portal.requests.'.$action, $submission), ['revision' => 1, 'message' => 'تعديل'])->assertNotFound();
        }
        $this->get(route('portal.orders.show', $order))->assertNotFound();
    }

    public function test_draft_ignores_client_prices_and_identity_and_never_creates_a_sales_order(): void
    {
        $user = $this->client();
        $other = $this->client();
        $data = $this->data();
        $data['customer_id'] = $other->customer_id;
        $data['lines'][0]['unit_price'] = 0.01;
        $data['lines'][0]['production_notes'] = 'INTERNAL-INJECTION';

        $this->actingAs($user)->post(route('portal.requests.store'), $data)->assertRedirect();

        $submission = CustomerPortalRequest::firstOrFail();
        $this->assertSame($user->customer_id, $submission->customer_id);
        $this->assertArrayNotHasKey('unit_price', $submission->lines[0]);
        $this->assertArrayNotHasKey('production_notes', $submission->lines[0]);
        $this->assertDatabaseCount('customer_orders', 0);
        $this->assertSame(0, (int) app(CustomerPortalAccountService::class)->account($user->customer)['summary']['outstanding']);
    }

    public function test_full_review_offer_confirmation_flow_creates_one_order_for_production_review(): void
    {
        $user = $this->client();
        $submission = $this->draft($user);
        $this->submit($user, $submission);
        $data = $this->offerData($submission->fresh());

        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $data)->assertRedirect(route('sales.portal-requests.show', $submission));
        $this->assertDatabaseCount('customer_orders', 0);
        $submission->refresh();
        $this->actingAs($user)->get(route('portal.requests.show', $submission))->assertSee('أوافق على العرض')->assertSee('500.00');
        $this->post(route('portal.requests.confirm', $submission), ['revision' => $submission->revision])->assertRedirect();

        $order = $submission->fresh()->order;
        $this->assertSame('PENDING_PRODUCTION_REVIEW', $order->status);
        $this->assertSame('500.00', $order->total_amount);
        $this->assertDatabaseHas('customer_order_lines', ['customer_order_id' => $order->id, 'line_type' => 'READY_STOCK', 'material_id' => $submission->lines[0]['material_id']]);
        $this->assertDatabaseHas('customer_portal_events', ['customer_portal_request_id' => $submission->id, 'event_type' => 'CONFIRMED']);
        $snapshot = $submission->events()->where('event_type', 'CONFIRMED')->first()->snapshot;
        $this->assertSame('500.00', $snapshot['offer_total']);
        $this->post(route('portal.requests.confirm', $submission), ['revision' => $submission->revision])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('customer_orders', 1);
    }

    public function test_revised_offer_cannot_be_accepted_from_an_old_page(): void
    {
        $user = $this->client();
        $submission = $this->offered($user);
        $oldRevision = $submission->revision;
        $data = $this->offerData($submission);
        $data['lines'][0]['unit_price'] = 300;
        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $data)->assertRedirect();

        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $oldRevision])->assertSessionHasErrors('revision');

        $this->assertDatabaseCount('customer_orders', 0);
        $this->assertSame('600.00', $submission->fresh()->offer_total);
    }

    public function test_customer_change_request_and_staff_return_allow_resubmission(): void
    {
        $user = $this->client();
        $submission = $this->offered($user);

        $this->actingAs($user)->post(route('portal.requests.changes', $submission), ['revision' => $submission->revision, 'message' => 'تغيير الكمية'])->assertRedirect();
        $data = $this->data($submission->lines[0]['material_id']);
        $data['revision'] = $submission->revision;
        $data['lines'][0]['quantity'] = 3;
        $this->put(route('portal.requests.update', $submission), $data)->assertRedirect();
        $submission->refresh();
        $this->assertSame('DRAFT', $submission->status);
        $this->assertNull($submission->offer_total);
        $this->submit($user, $submission);
        $this->actingAs($this->admin())->post(route('sales.portal-requests.return', $submission), ['revision' => $submission->revision, 'message' => 'حدد العنوان'])->assertRedirect();

        $this->assertSame('CHANGES_REQUESTED', $submission->fresh()->status);
    }

    public function test_confirmed_portal_request_and_its_sales_order_reference_each_other(): void
    {
        $user = $this->client();
        $submission = $this->draft($user);
        $this->submit($user, $submission);
        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission->fresh()));
        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->fresh()->revision]);
        $order = $submission->fresh()->order;

        $this->actingAs($this->admin())->get(route('sales.portal-requests.index'))
            ->assertOk()
            ->assertSee('PR-'.$submission->id)
            ->assertSee($order->order_number);

        $this->actingAs($this->admin())->get(route('sales.orders.show', $order))
            ->assertOk()
            ->assertSee('PR-'.$submission->id);
    }

    public function test_revision_after_confirmation_requires_new_acceptance_and_preserves_original_debt_until_then(): void
    {
        $user = $this->client();
        $submission = $this->confirmed($user);
        $order = $submission->order;
        $data = $this->offerData($submission);
        $data['lines'][0]['unit_price'] = 300;

        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $data)->assertRedirect();

        $this->assertSame('500.00', $order->fresh()->total_amount);
        try {
            app(CustomerOrderService::class)->approveForProduction($order, $this->admin());
            $this->fail('Unconfirmed revised offer must block production approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_confirmation', $exception->errors());
        }
        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->fresh()->revision])->assertRedirect();
        $this->assertSame('600.00', $order->fresh()->total_amount);
        $this->assertDatabaseCount('customer_orders', 1);
    }

    public function test_internal_order_edit_cannot_bypass_customer_confirmation(): void
    {
        $user = $this->client();
        $submission = $this->confirmed($user);

        $this->expectException(ValidationException::class);

        app(CustomerOrderService::class)->updateOrder($submission->order, [], [], $this->admin());
    }

    public function test_confirmed_orders_with_production_history_cannot_be_requoted(): void
    {
        $user = $this->client();
        $submission = $this->confirmed($user);
        ProductionOrder::create(['production_order_number' => 'PORTAL-PO-1', 'customer_order_id' => $submission->customer_order_id, 'customer_order_line_id' => $submission->order->lines->first()->id, 'ordered_quantity' => 2, 'released_quantity' => 2, 'status' => 'DRAFT']);

        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission))->assertSessionHasErrors('offer');

        $this->assertSame('CONFIRMED', $submission->fresh()->status);
    }

    public function test_account_excludes_drafts_unconfirmed_payments_and_other_customers_and_includes_opening_balance(): void
    {
        $this->travelTo(now()->startOfDay());
        $user = $this->client(['opening_balance' => 100]);
        $order = CustomerOrder::factory()->create(['customer_id' => $user->customer_id, 'status' => 'APPROVED_FOR_PRODUCTION', 'total_amount' => 1000, 'payment_due_date' => today()->subDay()]);
        CustomerOrder::factory()->create(['customer_id' => $user->customer_id, 'status' => 'DRAFT', 'total_amount' => 9000]);
        CustomerOrder::factory()->create(['customer_id' => $user->customer_id, 'status' => 'CANCELLED', 'total_amount' => 8000]);
        $payment = CustomerPayment::create(['payment_number' => 'PORTAL-PAY-1', 'customer_id' => $user->customer_id, 'payment_date' => today(), 'amount' => 400, 'payment_method' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'created_by_user_id' => $this->admin()->id]);
        CustomerPayment::create(['payment_number' => 'PORTAL-PENDING', 'customer_id' => $user->customer_id, 'payment_date' => today(), 'amount' => 500, 'payment_method' => 'BANK_TRANSFER', 'status' => 'PENDING_CONFIRMATION', 'created_by_user_id' => $this->admin()->id]);
        app(PaymentAllocationService::class)->allocatePayment($payment, $order, 400, 'FINAL_PAYMENT', $this->admin());
        $other = $this->client();
        CustomerOrder::factory()->create(['customer_id' => $other->customer_id, 'order_number' => 'PRIVATE-OTHER-ORDER', 'status' => 'APPROVED_FOR_PRODUCTION', 'total_amount' => 9999]);

        $account = app(CustomerPortalAccountService::class)->account($user->customer);
        $this->actingAs($user)->get(route('portal.account'))->assertSee('700.00')->assertDontSee('PRIVATE-OTHER-ORDER')->assertDontSee('PORTAL-PENDING');

        $this->assertSame(700, (int) $account['summary']['outstanding']);
        $this->assertSame(600, (int) $account['summary']['overdue']);
        $this->assertSame(700, (int) $account['entries']->last()['balance']);
    }

    public function test_historical_orders_are_visible_without_internal_notes(): void
    {
        $user = $this->client();
        $order = CustomerOrder::factory()->create(['customer_id' => $user->customer_id, 'order_number' => 'OLD-SHOWROOM-100', 'production_notes' => 'PRIVATE-FACTORY-NOTE', 'commercial_notes' => 'PRIVATE-COMMERCIAL-NOTE']);

        $this->actingAs($user)->get(route('portal.orders.index'))->assertSee('OLD-SHOWROOM-100');
        $this->get(route('portal.orders.show', $order))->assertSee('OLD-SHOWROOM-100')->assertDontSee('PRIVATE-FACTORY-NOTE')->assertDontSee('PRIVATE-COMMERCIAL-NOTE');
    }

    public function test_admin_can_create_only_one_account_for_each_customer(): void
    {
        $customer = Customer::factory()->create();
        $data = ['name' => 'حساب المعرض', 'username' => 'showroom.portal', 'role_id' => Role::where('name', 'customer_portal')->value('id'), 'customer_id' => $customer->id, 'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1];

        $this->actingAs($this->admin())->post(route('users.store'), $data)->assertRedirect(route('users.index'));
        $data['username'] = 'another.portal';
        $this->post(route('users.store'), $data)->assertSessionHasErrors('customer_id');

        $this->assertDatabaseHas('users', ['username' => 'showroom.portal', 'customer_id' => $customer->id]);
        $this->get(route('users.create'))->assertSee('العميل / المعرض');
    }

    public function test_worker_cannot_review_or_offer_and_customer_cannot_price_their_own_request(): void
    {
        $user = $this->client();
        $submission = $this->offered($user);
        $worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->value('id'), 'is_active' => true]);

        $this->actingAs($worker)->get(route('sales.portal-requests.index'))->assertForbidden();
        $this->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission))->assertForbidden();
        $this->actingAs($user)->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission))->assertForbidden();
    }

    public function test_invalid_products_quantities_and_offers_are_rejected(): void
    {
        $user = $this->client();
        $data = $this->data();
        $data['lines'][0]['quantity'] = -1;
        $this->actingAs($user)->post(route('portal.requests.store'), $data)->assertSessionHasErrors('lines.0.quantity');
        $submission = $this->offered($user);
        $offer = $this->offerData($submission);
        $offer['lines'][0]['discount_amount'] = 9999;

        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $offer)->assertSessionHasErrors('offer');
    }

    public function test_review_pages_and_customer_service_workflow_are_accessible(): void
    {
        $user = $this->client();
        $submission = $this->draft($user);
        $this->submit($user, $submission);
        $staff = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->value('id'), 'is_active' => true]);

        $this->actingAs($staff)->get(route('sales.portal-requests.index'))->assertSee($user->customer->name);
        $this->get(route('sales.portal-requests.show', $submission))->assertSee('إرسال العرض للعميل');
        $this->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission->fresh()))->assertRedirect();

        $this->assertSame('OFFERED', $submission->fresh()->status);
    }

    public function test_manufactured_offer_links_reference_configuration_and_recipe_without_changing_requested_dimensions(): void
    {
        $user = $this->client();
        $model = ProductModel::create(['model_code' => 'PORTAL-BED', 'name_ar' => 'سرير المعرض', 'is_active' => true, 'requires_fabric_selection' => false]);
        $configuration = ProductConfiguration::create(['configuration_code' => 'PORTAL-CONFIG', 'product_model_id' => $model->id, 'width_cm' => 100, 'length_cm' => 200, 'has_storage' => false, 'is_active' => true]);
        $recipe = ManufacturingRecipe::factory()->create(['product_configuration_id' => $configuration->id]);
        $version = ManufacturingRecipeVersion::factory()->create(['manufacturing_recipe_id' => $recipe->id, 'status' => 'APPROVED']);
        $line = ['line_type' => 'MANUFACTURED', 'product_model_id' => $model->id, 'requested_width_cm' => 95, 'requested_length_cm' => 210, 'quantity' => 1, 'has_storage' => 0];
        $this->actingAs($user)->post(route('portal.requests.store'), ['lines' => [$line]])->assertRedirect();
        $submission = CustomerPortalRequest::firstOrFail();
        $this->submit($user, $submission);
        $offer = $this->offerData($submission->fresh());
        $offer['lines'][0]['product_configuration_id'] = $configuration->id;
        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $offer)->assertRedirect();

        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->fresh()->revision])->assertRedirect();

        $this->assertDatabaseHas('customer_order_lines', ['requested_width_cm' => 95, 'requested_length_cm' => 210, 'reference_width_cm' => 100, 'reference_length_cm' => 200, 'product_configuration_id' => $configuration->id, 'approved_recipe_version_id' => $version->id]);
        $order = $submission->fresh()->order;
        app(CustomerOrderService::class)->approveForProduction($order, $this->admin());
        $po = app(ProductionOrderService::class)->createFromOrderLine($order->lines->first(), ['released_quantity' => 1]);
        $this->assertSame($version->id, $po->manufacturing_recipe_version_id);
    }

    public function test_customer_cannot_submit_or_confirm_before_the_required_steps(): void
    {
        $user = $this->client();
        $submission = $this->draft($user);

        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->revision])->assertSessionHasErrors('status');
        $this->submit($user, $submission);
        $this->post(route('portal.requests.submit', $submission), ['revision' => $submission->revision])->assertSessionHasErrors('status');
        $this->get(route('portal.requests.edit', $submission))->assertForbidden();

        $this->assertDatabaseCount('customer_orders', 0);
    }

    public function test_customer_messages_are_escaped_and_confirmed_changes_are_only_requests(): void
    {
        $user = $this->client();
        $submission = $this->confirmed($user);
        $message = '<script>alert("private")</script>';

        $this->actingAs($user)->post(route('portal.requests.changes', $submission), ['revision' => $submission->revision, 'message' => $message])->assertRedirect();
        $this->get(route('portal.requests.show', $submission))->assertSee('&lt;script&gt;', false)->assertDontSee($message, false);

        $this->assertSame('CONFIRMED', $submission->fresh()->status);
        $this->assertSame('500.00', $submission->fresh()->order->total_amount);
    }

    public function test_portal_account_shows_advance_credit_and_reconciles_with_internal_statement(): void
    {
        $user = $this->client(['opening_balance' => 50]);
        CustomerPayment::create(['payment_number' => 'ADVANCE-1', 'customer_id' => $user->customer_id, 'payment_date' => today(), 'amount' => 200, 'payment_method' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'created_by_user_id' => $this->admin()->id]);
        CustomerOrder::factory()->create(['customer_id' => $user->customer_id, 'status' => 'DRAFT', 'total_amount' => 900]);

        $account = app(CustomerPortalAccountService::class)->account($user->customer);
        $statement = app(ReceivablesReportingService::class)->getCustomerStatement($user->customer);

        $this->assertSame(150, (int) $account['summary']['credit']);
        $this->assertSame(-150, (int) $statement->last()['running_balance']);
        $this->assertSame(-150, (int) $account['entries']->last()['balance']);
    }

    public function test_account_creation_requires_a_customer_and_rejects_linking_staff_to_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $data = ['name' => 'عميل', 'username' => 'portal.missing', 'role_id' => Role::where('name', 'customer_portal')->value('id'), 'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1];

        $this->actingAs($this->admin())->post(route('users.store'), $data)->assertSessionHasErrors('customer_id');
        $data['role_id'] = Role::where('name', 'customer_service')->value('id');
        $data['customer_id'] = $customer->id;
        $this->post(route('users.store'), $data)->assertSessionHasErrors('customer_id');

        $this->assertDatabaseMissing('users', ['username' => 'portal.missing']);
    }

    public function test_mixed_customer_request_preserves_manufactured_and_ready_stock_lines(): void
    {
        $user = $this->client();
        $model = ProductModel::factory()->create();
        $data = $this->data();
        $data['lines'][] = ['line_type' => 'MANUFACTURED', 'product_model_id' => $model->id, 'requested_width_cm' => 100, 'requested_length_cm' => 200, 'quantity' => 1];
        $this->actingAs($user)->post(route('portal.requests.store'), $data)->assertRedirect();
        $submission = CustomerPortalRequest::firstOrFail();
        $this->submit($user, $submission);
        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission->fresh()))->assertRedirect();

        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->fresh()->revision])->assertRedirect();

        $this->assertDatabaseHas('customer_order_lines', ['customer_order_id' => $submission->fresh()->customer_order_id, 'line_type' => 'READY_STOCK', 'material_id' => $data['lines'][0]['material_id']]);
        $this->assertDatabaseHas('customer_order_lines', ['customer_order_id' => $submission->fresh()->customer_order_id, 'line_type' => 'MANUFACTURED', 'product_model_id' => $model->id]);
    }

    public function test_partial_receipts_are_counted_as_received_quantities_and_do_not_expose_other_deliveries(): void
    {
        $user = $this->client();
        $submission = $this->confirmed($user);
        $order = $submission->order;
        $delivery = DeliveryOrder::create(['delivery_number' => 'PORTAL-DELIVERY', 'customer_order_id' => $order->id, 'customer_id' => $user->customer_id, 'customer_name_snapshot' => $user->customer->name, 'sales_channel_id' => $order->sales_channel_id, 'status' => 'DELIVERED', 'delivered_at' => now(), 'created_by_user_id' => $this->admin()->id]);
        $delivery->lines()->create(['line_type' => 'READY_STOCK', 'material_id' => $submission->lines[0]['material_id'], 'quantity' => 1]);
        $other = $this->client();
        $otherOrder = CustomerOrder::factory()->create(['customer_id' => $other->customer_id]);
        DeliveryOrder::create(['delivery_number' => 'PRIVATE-OTHER-DELIVERY', 'customer_order_id' => $otherOrder->id, 'customer_id' => $other->customer_id, 'customer_name_snapshot' => $other->customer->name, 'sales_channel_id' => $otherOrder->sales_channel_id, 'status' => 'DELIVERED', 'delivered_at' => now(), 'created_by_user_id' => $this->admin()->id]);

        $progress = app(CustomerPortalAccountService::class)->progress($order);
        $this->actingAs($user)->get(route('portal.receipts'))->assertSee('PORTAL-DELIVERY')->assertDontSee('PRIVATE-OTHER-DELIVERY');

        $this->assertSame('تم التسليم جزئيًا', $progress['status']);
        $this->assertSame(1, (int) $progress['delivered_quantity']);
        $this->assertSame(2, (int) $progress['ordered_quantity']);
    }

    public function test_custom_design_image_and_free_fabric_request_are_reviewed_and_transferred_to_the_confirmed_order(): void
    {
        Storage::fake('local');
        $user = $this->client();
        $line = [
            'line_type' => 'MANUFACTURED', 'custom_design' => true, 'custom_design_name' => 'سرير بصورة العميل',
            'requested_width_cm' => 160, 'requested_length_cm' => 200, 'quantity' => 2,
            'requested_fabric' => 'مخمل كتالوج المعرض', 'requested_color' => 'بيج رملي',
            'custom_design_image' => UploadedFile::fake()->image('design.png'),
            'custom_design_image_path' => 'forged.png', 'unit_price' => 1,
        ];
        $this->actingAs($user)->get(route('portal.requests.create'))->assertSee('إضافة منتج جاهز')->assertSee('تصميم خاص حسب الطلب')->assertSee('وصف القماش الخاص المطلوب');
        $this->post(route('portal.requests.store'), ['lines' => [$line]])->assertSessionHasNoErrors();
        $submission = CustomerPortalRequest::firstOrFail();
        $saved = $submission->lines[0];
        $this->assertSame('سرير بصورة العميل', $saved['product_name']);
        $this->assertArrayNotHasKey('unit_price', $saved);
        $this->assertNotSame('forged.png', $saved['custom_design_image_path']);
        Storage::disk('local')->assertExists($saved['custom_design_image_path']);
        $url = route('portal.requests.design-image', [$submission, $saved['line_key']]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('portal.requests.show', $submission))->assertSee('مخمل كتالوج المعرض')->assertSee('بيج رملي')->assertSee($url);
        $this->actingAs($this->client())->get($url)->assertNotFound();
        $this->submit($user, $submission);

        $offer = $this->offerData($submission->fresh());
        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $offer)->assertSessionHasNoErrors();
        $this->get(route('sales.portal-requests.design-image', [$submission, $saved['line_key']]))->assertOk();
        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->fresh()->revision])->assertSessionHasNoErrors();

        $confirmedLine = $submission->fresh()->order->lines->first();
        $this->assertTrue($confirmedLine->custom_design);
        $this->assertSame($saved['custom_design_image_path'], $confirmedLine->custom_design_image_path);
        $this->assertNull($confirmedLine->fabric_material_id);
        $this->assertNull($confirmedLine->fabric_color_id);
        $this->assertTrue($confirmedLine->special_fabric);
        $this->assertStringContainsString('بيج رملي', $confirmedLine->fabric_notes);
        $this->assertSame('بيج رملي', $submission->fresh()->lines[0]['requested_color']);
    }

    public function test_draft_images_keep_their_line_identity_after_reordering_and_ignore_foreign_paths(): void
    {
        Storage::fake('local');
        $user = $this->client();
        $base = ['line_type' => 'MANUFACTURED', 'custom_design' => true, 'requested_width_cm' => 100, 'requested_length_cm' => 200, 'quantity' => 1];
        $lines = [
            array_merge($base, ['custom_design_name' => 'التصميم الأول', 'custom_design_image' => UploadedFile::fake()->image('one.png')]),
            array_merge($base, ['custom_design_name' => 'التصميم الثاني', 'custom_design_image' => UploadedFile::fake()->image('two.png')]),
        ];
        $this->actingAs($user)->post(route('portal.requests.store'), ['lines' => $lines])->assertSessionHasNoErrors();
        $submission = CustomerPortalRequest::firstOrFail();
        $original = $submission->lines;
        $reordered = array_reverse($original);
        $reordered[0]['custom_design_image_path'] = 'forged/another-customer.png';

        $this->put(route('portal.requests.update', $submission), ['revision' => $submission->revision, 'lines' => $reordered])->assertSessionHasNoErrors();

        $this->assertSame($original[1]['custom_design_image_path'], $submission->fresh()->lines[0]['custom_design_image_path']);
        $this->assertSame($original[0]['custom_design_image_path'], $submission->fresh()->lines[1]['custom_design_image_path']);
    }

    public function test_portal_rejects_invalid_images_and_removes_uploads_when_an_offer_fails(): void
    {
        Storage::fake('local');
        $user = $this->client();
        $line = ['line_type' => 'MANUFACTURED', 'custom_design' => true, 'custom_design_name' => 'خاص', 'requested_width_cm' => 100, 'requested_length_cm' => 200, 'quantity' => 1];
        $this->actingAs($user)->post(route('portal.requests.store'), ['lines' => [array_merge($line, ['custom_design_image' => UploadedFile::fake()->create('not-image.pdf', 1, 'application/pdf')])]])->assertSessionHasErrors('lines.0.custom_design_image');
        $this->post(route('portal.requests.store'), ['lines' => [$line]])->assertSessionHasNoErrors();
        $submission = CustomerPortalRequest::firstOrFail();
        $this->submit($user, $submission);
        $offer = $this->offerData($submission->fresh());
        $offer['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('rollback.png');
        $offer['lines'][0]['discount_amount'] = 9999;

        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $offer)->assertSessionHasErrors('offer');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('SUBMITTED', $submission->fresh()->status);
    }

    public function test_customer_can_select_a_model_configuration_without_setting_the_price(): void
    {
        $user = $this->client();
        $model = ProductModel::factory()->create(['requires_fabric_selection' => false]);
        $configuration = ProductConfiguration::factory()->create(['product_model_id' => $model->id, 'has_storage' => false]);
        $line = ['line_type' => 'MANUFACTURED', 'product_model_id' => $model->id, 'product_configuration_id' => $configuration->id,
            'requested_width_cm' => 160, 'requested_length_cm' => 200, 'has_storage' => 0, 'quantity' => 1, 'unit_price' => 99];

        $this->actingAs($user)->post(route('portal.requests.store'), ['lines' => [$line]])->assertSessionHasNoErrors();

        $saved = CustomerPortalRequest::firstOrFail()->lines[0];
        $this->assertSame($configuration->id, $saved['product_configuration_id']);
        $this->assertArrayNotHasKey('unit_price', $saved);
    }

    public function test_customer_can_switch_between_custom_and_catalog_fabric_without_stale_values(): void
    {
        $user = $this->client();
        $fabric = Material::factory()->create(['material_category_id' => MaterialCategory::where('code', 'FABRIC')->value('id')]);
        $color = FabricColor::factory()->create(['material_id' => $fabric->id, 'is_available' => true]);
        $line = ['line_type' => 'MANUFACTURED', 'custom_design' => true, 'custom_design_name' => 'سرير خاص',
            'requested_width_cm' => 160, 'requested_length_cm' => 200, 'quantity' => 1,
            'fabric_source' => 'CUSTOM', 'fabric_material_id' => $fabric->id, 'fabric_color_id' => $color->id,
            'requested_fabric' => 'كتالوج العميل', 'requested_color' => 'بيج خاص'];

        $this->actingAs($user)->post(route('portal.requests.store'), ['lines' => [$line]])->assertSessionHasNoErrors();

        $submission = CustomerPortalRequest::firstOrFail();
        $saved = $submission->lines[0];
        $this->assertSame('CUSTOM', $saved['fabric_source']);
        $this->assertNull($saved['fabric_material_id']);
        $this->assertNull($saved['fabric_color_id']);
        $this->assertSame('كتالوج العميل', $saved['requested_fabric']);
        $saved['fabric_source'] = 'CATALOG';
        $saved['fabric_material_id'] = $fabric->id;
        $saved['fabric_color_id'] = $color->id;

        $this->put(route('portal.requests.update', $submission), ['revision' => $submission->revision, 'lines' => [$saved]])->assertSessionHasNoErrors();

        $catalog = $submission->fresh()->lines[0];
        $this->assertSame($fabric->id, $catalog['fabric_material_id']);
        $this->assertSame($color->id, $catalog['fabric_color_id']);
        $this->assertNull($catalog['requested_fabric']);
        $this->assertNull($catalog['requested_color']);
        $this->assertNull($catalog['fabric_notes']);
        $this->get(route('portal.requests.edit', $submission))->assertSee('كتالوج خاص / قماش خاص')->assertDontSee('يُستكمل مع خدمة العملاء');
    }

    public function test_staff_keeps_original_custom_fabric_request_when_mapping_it_to_inventory(): void
    {
        $user = $this->client();
        $line = ['line_type' => 'MANUFACTURED', 'custom_design' => true, 'custom_design_name' => 'سرير خاص',
            'requested_width_cm' => 160, 'requested_length_cm' => 200, 'quantity' => 1,
            'fabric_source' => 'CUSTOM', 'requested_fabric' => 'كتالوج العميل', 'requested_color' => 'بيج خاص'];
        $this->actingAs($user)->post(route('portal.requests.store'), ['lines' => [$line]])->assertSessionHasNoErrors();
        $submission = CustomerPortalRequest::firstOrFail();
        $this->submit($user, $submission);
        $fabric = Material::factory()->create(['material_category_id' => MaterialCategory::where('code', 'FABRIC')->value('id')]);
        $color = FabricColor::factory()->create(['material_id' => $fabric->id, 'is_available' => true]);
        $offer = $this->offerData($submission->fresh());
        unset($offer['lines'][0]['fabric_source']);
        $offer['lines'][0]['fabric_material_id'] = $fabric->id;
        $offer['lines'][0]['fabric_color_id'] = $color->id;
        $offer['lines'][0]['requested_fabric'] = 'تغيير غير مطلوب للوصف الأصلي';

        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $offer)->assertSessionHasNoErrors();

        $saved = $submission->fresh()->lines[0];
        $this->assertSame('CUSTOM', $saved['fabric_source']);
        $this->assertSame('كتالوج العميل', $saved['requested_fabric']);
        $this->assertSame('بيج خاص', $saved['requested_color']);
        $this->assertSame($fabric->id, $saved['fabric_material_id']);
        $this->get(route('sales.portal-requests.show', $submission))->assertSee('طلب العميل الأصلي محفوظ');
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    protected function client(array $attributes = []): User
    {
        $customer = Customer::factory()->create($attributes);

        return User::factory()->create(['role_id' => Role::where('name', 'customer_portal')->value('id'), 'customer_id' => $customer->id, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    protected function data(?int $materialId = null): array
    {
        $materialId ??= Material::factory()->create(['material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->value('id'), 'base_unit_id' => UnitOfMeasure::where('code', 'PIECE')->value('id'), 'is_sellable_ready_product' => true])->id;

        return ['customer_reference' => 'SHOWROOM-REF', 'lines' => [['line_type' => 'READY_STOCK', 'material_id' => $materialId, 'quantity' => 2]]];
    }

    protected function draft(User $user): CustomerPortalRequest
    {
        return app(CustomerPortalService::class)->saveDraft($user, $this->data());
    }

    protected function submit(User $user, CustomerPortalRequest $submission): void
    {
        $this->actingAs($user)->post(route('portal.requests.submit', $submission), ['revision' => $submission->revision])->assertRedirect();
    }

    /** @return array<string, mixed> */
    protected function offerData(CustomerPortalRequest $submission): array
    {
        $lines = $submission->lines;
        foreach ($lines as &$line) {
            $line['unit_price'] = 250;
            $line['discount_amount'] = 0;
        }
        unset($line);

        return ['revision' => $submission->revision, 'lines' => $lines, 'promised_delivery_date' => today()->addDays(10)->toDateString(), 'payment_terms_type' => 'CASH_ON_DELIVERY'];
    }

    protected function offered(User $user): CustomerPortalRequest
    {
        $submission = $this->draft($user);
        $this->submit($user, $submission);
        $submission->refresh();
        $this->actingAs($this->admin())->post(route('sales.portal-requests.offer', $submission), $this->offerData($submission))->assertRedirect();

        return $submission->fresh();
    }

    protected function confirmed(User $user): CustomerPortalRequest
    {
        $submission = $this->offered($user);
        $this->actingAs($user)->post(route('portal.requests.confirm', $submission), ['revision' => $submission->revision])->assertRedirect();

        return $submission->fresh();
    }
}
