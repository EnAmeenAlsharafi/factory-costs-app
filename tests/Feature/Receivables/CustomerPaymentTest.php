<?php

namespace Tests\Feature\Receivables;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerType;
use App\Models\PaymentControlOverride;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\CustomerCreditService;
use App\Services\CustomerOrderService;
use App\Services\CustomerPaymentService;
use App\Services\OrderPaymentEligibilityService;
use App\Services\PaymentAllocationService;
use App\Services\ReceivablesReportingService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $receivablesUser;

    protected User $productionWorker;

    protected Customer $customer;

    protected SalesChannel $salesChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $receivablesRole = Role::where('name', 'receivables_user')->first();
        $this->receivablesUser = User::factory()->create(['role_id' => $receivablesRole->id, 'is_active' => true]);

        $prodWorkerRole = Role::where('name', 'production_worker')->first();
        $this->productionWorker = User::factory()->create(['role_id' => $prodWorkerRole->id, 'is_active' => true]);

        $customerType = CustomerType::firstOrCreate(
            ['code' => 'WHOLESALE'],
            ['name_ar' => 'عميل جملة', 'is_active' => true]
        );

        $this->customer = Customer::create([
            'customer_code' => 'CUS-100001',
            'customer_type_id' => $customerType->id,
            'name' => 'شركة الأعمال المتقدمة',
            'is_active' => true,
            'is_credit_customer' => true,
            'credit_limit' => 50000.00,
        ]);

        $this->salesChannel = SalesChannel::firstOrCreate(
            ['code' => 'SHOWROOM'],
            ['name_ar' => 'المعارض المباشرة', 'is_active' => true]
        );
    }

    protected function createOrder(float $totalAmount = 10000.00, string $terms = 'FULL_BEFORE_PRODUCTION', array $extra = []): CustomerOrder
    {
        $order = CustomerOrder::create(array_merge([
            'order_number' => 'ORD-'.rand(10000, 99999),
            'customer_id' => $this->customer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'status' => 'DRAFT',
            'payment_terms_type' => $terms,
            'subtotal' => $totalAmount,
            'discount_total' => 0.00,
            'total_amount' => $totalAmount,
            'created_by_user_id' => $this->admin->id,
        ], $extra));

        CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'custom_design' => true,
            'custom_design_name' => 'سرير فاخر 200*200',
            'requested_width_cm' => 200,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => $totalAmount,
            'line_total' => $totalAmount,
        ]);

        return $order->fresh();
    }

    public function test_basic_payment_creation_confirmation_and_allocation()
    {
        $order = $this->createOrder(10000.00);

        $paymentService = app(CustomerPaymentService::class);
        $allocationService = app(PaymentAllocationService::class);

        // 1. Create Pending Payment
        $payment = $paymentService->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 4000.00,
            'payment_method' => 'BANK_TRANSFER',
            'reference_number' => 'REF-12345',
            'status' => 'PENDING_CONFIRMATION',
        ], $this->receivablesUser);

        $this->assertEquals('PENDING_CONFIRMATION', $payment->status);
        $this->assertEquals(0.00, $order->confirmed_paid_amount);
        $this->assertEquals(10000.00, $order->outstanding_balance);

        // 2. Confirm Payment
        $paymentService->confirmPayment($payment, $this->receivablesUser);
        $this->assertEquals('CONFIRMED', $payment->fresh()->status);
        $this->assertEquals(4000.00, $payment->fresh()->unallocated_amount);

        // 3. Allocate Payment to Order
        $allocation = $allocationService->allocatePayment($payment, $order, 4000.00, 'PARTIAL_PAYMENT', $this->receivablesUser);

        $this->assertEquals(4000.00, $allocation->allocated_amount);
        $this->assertEquals(4000.00, $order->fresh()->confirmed_paid_amount);
        $this->assertEquals(6000.00, $order->fresh()->outstanding_balance);
        $this->assertEquals(0.00, $payment->fresh()->unallocated_amount);
    }

    public function test_multi_order_payment_allocation()
    {
        $orderA = $this->createOrder(6000.00);
        $orderB = $this->createOrder(7000.00);

        $paymentService = app(CustomerPaymentService::class);
        $allocationService = app(PaymentAllocationService::class);

        $payment = $paymentService->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 10000.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'CONFIRMED',
        ], $this->receivablesUser);

        $allocationService->allocatePayment($payment, $orderA, 6000.00, 'FINAL_PAYMENT', $this->receivablesUser);
        $allocationService->allocatePayment($payment, $orderB, 4000.00, 'PARTIAL_PAYMENT', $this->receivablesUser);

        $this->assertEquals(6000.00, $orderA->fresh()->confirmed_paid_amount);
        $this->assertEquals(0.00, $orderA->fresh()->outstanding_balance);

        $this->assertEquals(4000.00, $orderB->fresh()->confirmed_paid_amount);
        $this->assertEquals(3000.00, $orderB->fresh()->outstanding_balance);

        $this->assertEquals(0.00, $payment->fresh()->unallocated_amount);
    }

    public function test_over_allocation_is_rejected()
    {
        $order = $this->createOrder(5000.00);
        $paymentService = app(CustomerPaymentService::class);
        $allocationService = app(PaymentAllocationService::class);

        $payment = $paymentService->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 4000.00,
            'payment_method' => 'CASH',
            'status' => 'CONFIRMED',
        ], $this->receivablesUser);

        $this->expectException(\Exception::class);
        $allocationService->allocatePayment($payment, $order, 6000.00, 'PARTIAL_PAYMENT', $this->receivablesUser);
    }

    public function test_wrong_customer_allocation_is_rejected()
    {
        $customerType = CustomerType::firstOrCreate(['code' => 'WHOLESALE'], ['name_ar' => 'عميل جملة']);
        $otherCustomer = Customer::create(['customer_code' => 'CUS-999999', 'customer_type_id' => $customerType->id, 'name' => 'عميل آخر', 'is_active' => true]);
        $orderOther = CustomerOrder::create([
            'order_number' => 'ORD-999',
            'customer_id' => $otherCustomer->id,
            'sales_channel_id' => $this->salesChannel->id,
            'order_date' => now()->toDateString(),
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
            'created_by_user_id' => $this->admin->id,
        ]);

        $payment = app(CustomerPaymentService::class)->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 3000.00,
            'payment_method' => 'CASH',
            'status' => 'CONFIRMED',
        ], $this->receivablesUser);

        $this->expectException(\Exception::class);
        app(PaymentAllocationService::class)->allocatePayment($payment, $orderOther, 3000.00, 'PARTIAL_PAYMENT', $this->receivablesUser);
    }

    public function test_payment_reversal_resets_allocations_and_preserves_audit_trail()
    {
        $order = $this->createOrder(10000.00);
        $paymentService = app(CustomerPaymentService::class);
        $allocationService = app(PaymentAllocationService::class);

        $payment = $paymentService->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 10000.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'CONFIRMED',
        ], $this->receivablesUser);

        $allocationService->allocatePayment($payment, $order, 10000.00, 'FINAL_PAYMENT', $this->receivablesUser);
        $this->assertEquals(0.00, $order->fresh()->outstanding_balance);

        // Reverse Payment
        $paymentService->reversePayment($payment, $this->receivablesUser, 'خطأ في التحويل البنكي');

        $this->assertEquals('REVERSED', $payment->fresh()->status);
        $this->assertEquals(0.00, $order->fresh()->confirmed_paid_amount);
        $this->assertEquals(10000.00, $order->fresh()->outstanding_balance);
        $this->assertDatabaseHas('customer_payment_events', [
            'customer_payment_id' => $payment->id,
            'event_type' => 'REVERSED',
        ]);
    }

    public function test_deposit_and_full_prepayment_production_gates()
    {
        $eligibilityService = app(OrderPaymentEligibilityService::class);
        $paymentService = app(CustomerPaymentService::class);
        $allocationService = app(PaymentAllocationService::class);

        // 1. Full Prepayment Order
        $orderFull = $this->createOrder(10000.00, 'FULL_BEFORE_PRODUCTION');
        $check1 = $eligibilityService->checkProductionEligibility($orderFull);
        $this->assertFalse($check1['eligible']);

        $pay1 = $paymentService->createPayment(['customer_id' => $this->customer->id, 'payment_date' => now()->toDateString(), 'amount' => 10000.00, 'payment_method' => 'CASH', 'status' => 'CONFIRMED'], $this->receivablesUser);
        $allocationService->allocatePayment($pay1, $orderFull, 10000.00, 'FINAL_PAYMENT', $this->receivablesUser);
        $this->assertTrue($eligibilityService->checkProductionEligibility($orderFull)['eligible']);

        // 2. Deposit & Balance Order
        $orderDeposit = $this->createOrder(10000.00, 'DEPOSIT_AND_BALANCE', ['deposit_required_amount' => 3000.00]);
        $check2 = $eligibilityService->checkProductionEligibility($orderDeposit);
        $this->assertFalse($check2['eligible']);

        $pay2 = $paymentService->createPayment(['customer_id' => $this->customer->id, 'payment_date' => now()->toDateString(), 'amount' => 3000.00, 'payment_method' => 'CASH', 'status' => 'CONFIRMED'], $this->receivablesUser);
        $allocationService->allocatePayment($pay2, $orderDeposit, 3000.00, 'DEPOSIT', $this->receivablesUser);
        $this->assertTrue($eligibilityService->checkProductionEligibility($orderDeposit)['eligible']);
    }

    public function test_credit_limit_enforcement_and_override()
    {
        $creditService = app(CustomerCreditService::class);

        // Setup profile: Limit 50,000 SAR, hold enabled
        $profile = $creditService->getCustomerCreditProfile($this->customer);
        $profile->update(['credit_enabled' => true, 'credit_limit' => 50000.00, 'hold_when_exceeded' => true]);

        // Create orders totaling 60,000 SAR exposure
        $order1 = $this->createOrder(60000.00, 'CREDIT');

        $eligibilityService = app(OrderPaymentEligibilityService::class);
        $check = $eligibilityService->checkProductionEligibility($order1);
        $this->assertFalse($check['eligible']);

        // Authorized User Overrides
        PaymentControlOverride::create([
            'customer_order_id' => $order1->id,
            'customer_id' => $this->customer->id,
            'override_stage' => 'PRODUCTION_RELEASE',
            'requested_amount' => 60000.00,
            'reason' => 'موافقة الإدارة العليا لعميل استراتيجي',
            'created_by_user_id' => $this->admin->id,
        ]);

        $checkAfterOverride = $eligibilityService->checkProductionEligibility($order1);
        $this->assertTrue($checkAfterOverride['eligible']);
        $this->assertEquals('OVERRIDDEN', $checkAfterOverride['status']);
    }

    public function test_aging_report_bucket_classification()
    {
        // 1. Current Order
        $orderCurrent = $this->createOrder(5000.00, 'CREDIT', ['payment_due_date' => now()->addDays(10)->toDateString()]);

        // 2. 45 Days Overdue Order
        $order45 = $this->createOrder(8000.00, 'CREDIT', ['payment_due_date' => now()->subDays(45)->toDateString()]);

        $reportingService = app(ReceivablesReportingService::class);
        $aging = $reportingService->getAgingReport();

        $this->assertEquals(5000.00, $aging['current']['amount']);
        $this->assertEquals(8000.00, $aging['31_60']['amount']);
    }

    public function test_order_cancellation_releases_allocations_to_unallocated_credit()
    {
        $order = $this->createOrder(10000.00);
        $payment = app(CustomerPaymentService::class)->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 4000.00,
            'payment_method' => 'CASH',
            'status' => 'CONFIRMED',
        ], $this->receivablesUser);

        app(PaymentAllocationService::class)->allocatePayment($payment, $order, 4000.00, 'PARTIAL_PAYMENT', $this->receivablesUser);
        $this->assertEquals(0.00, $payment->fresh()->unallocated_amount);

        // Cancel order
        app(CustomerOrderService::class)->cancelOrder($order, $this->admin, 'طلب العميل الإلغاء');

        $this->assertEquals(4000.00, $payment->fresh()->unallocated_amount);
        $this->assertEquals(0, $order->fresh()->allocations()->count());
    }

    public function test_cost_and_financial_privacy_enforcement()
    {
        // Receivables User CAN view payments & balances
        $response1 = $this->actingAs($this->receivablesUser)->get(route('receivables.payments.index'));
        $response1->assertOk();

        // Production Worker CANNOT access detailed payment register
        $response2 = $this->actingAs($this->productionWorker)->get(route('receivables.payments.index'));
        $response2->assertStatus(403);
    }

    public function test_monetary_reconciliation_equation()
    {
        $payment = app(CustomerPaymentService::class)->createPayment([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 20000.00,
            'payment_method' => 'BANK_TRANSFER',
            'status' => 'CONFIRMED',
        ], $this->receivablesUser);

        $orderA = $this->createOrder(7000.00);
        $orderB = $this->createOrder(8000.00);

        app(PaymentAllocationService::class)->allocatePayment($payment, $orderA, 7000.00, 'FINAL_PAYMENT', $this->receivablesUser);
        app(PaymentAllocationService::class)->allocatePayment($payment, $orderB, 8000.00, 'FINAL_PAYMENT', $this->receivablesUser);

        // Equation 1: Confirmed Payment Amount == Allocated + Unallocated
        $allocatedSum = $payment->allocated_amount;
        $unallocated = $payment->unallocated_amount;
        $this->assertEquals((float) $payment->amount, $allocatedSum + $unallocated);

        // Equation 2: Order Commercial Total == Confirmed Paid + Outstanding
        $this->assertEquals((float) $orderA->total_amount, $orderA->confirmed_paid_amount + $orderA->outstanding_balance);
    }
}
