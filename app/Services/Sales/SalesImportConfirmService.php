<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerType;
use App\Models\SalesImportBatch;
use App\Models\SalesImportRow;
use App\Models\SalesItemMapping;
use App\Models\User;
use App\Services\CustomerOrderService;
use App\Services\CustomerPaymentService;
use App\Services\DocumentNumberService;
use App\Services\PaymentAllocationService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a staged import batch: actually creates customers/orders/
 * payments (spec §6.3 step 3, §6.4, §6.5). Each order is its own
 * transaction so one bad order never cancels the batch.
 */
class SalesImportConfirmService
{
    private const PAID_STATUSES = ['مدفوع', 'paid'];

    public function __construct(
        protected SalesItemMatchingService $matcher,
        protected SalesOrderClassifier $classifier,
        protected CustomerOrderService $orderService,
        protected CustomerPaymentService $paymentService,
        protected PaymentAllocationService $allocationService,
    ) {}

    public function confirm(SalesImportBatch $batch, User $confirmer): SalesImportBatch
    {
        $rows = $batch->rows()->whereNull('outcome')->get();

        foreach ($rows as $row) {
            $this->confirmRow($batch, $row, $confirmer);
        }

        $batch->update([
            'created_count' => $batch->rows()->where('outcome', 'CREATED')->count(),
            'skipped_count' => $batch->rows()->where('outcome', 'SKIPPED')->count(),
            'failed_count' => $batch->rows()->whereIn('outcome', ['FAILED', 'DUPLICATE'])->count(),
            'status' => 'CONFIRMED',
        ]);

        return $batch->fresh('rows');
    }

    protected function confirmRow(SalesImportBatch $batch, SalesImportRow $row, User $confirmer): void
    {
        $data = $row->raw_data;
        $lines = $data['lines'] ?? [];

        $targetTypes = [];
        $resolutions = [];
        foreach ($lines as $line) {
            $resolution = $this->matcher->resolveForImport($batch->salesChannel, $line['sku'] ?? null, $line['product_name'] ?? null);
            $resolutions[] = $resolution;
            $targetTypes[] = $resolution['target_type'];
        }

        $classification = $this->classifier->classify($data, $targetTypes);

        if ($classification['decision'] === 'SKIP') {
            $row->update(['outcome' => 'SKIPPED', 'message' => $classification['skip_reason']]);

            return;
        }

        $alreadyExists = CustomerOrder::where('sales_channel_id', $batch->sales_channel_id)
            ->where('external_order_reference', $row->external_order_reference)
            ->exists();

        if ($alreadyExists) {
            $row->update(['outcome' => 'DUPLICATE', 'message' => 'يوجد طلب مستورَد مسبقاً بنفس رقم طلب سلة.']);

            return;
        }

        try {
            DB::transaction(function () use ($batch, $row, $data, $lines, $resolutions, $confirmer) {
                $customer = $this->resolveOrCreateCustomer($batch, $data);
                $linesData = $this->buildLinesData($lines, $resolutions);
                $manualLines = array_values(array_filter($lines, fn ($line, $index) => $resolutions[$index]['target_type'] !== 'READY_STOCK', ARRAY_FILTER_USE_BOTH));
                $hasIncompleteManufactured = $manualLines !== [];

                $order = $this->orderService->createOrder([
                    'customer_id' => $customer->id,
                    'sales_channel_id' => $batch->sales_channel_id,
                    'external_order_reference' => $row->external_order_reference,
                    'external_order_internal_id' => $data['external_order_internal_id'] ?? null,
                    'external_payment_method' => $data['payment_method'] ?? null,
                    'external_payment_status' => $data['payment_status'] ?? null,
                    'import_batch_id' => $batch->id,
                    'manual_import_lines' => $manualLines ?: null,
                    'delivery_method' => in_array($data['shipping_method'] ?? null, SalesOrderClassifier::INTERNAL_SHIPPING_METHODS, true) ? 'INTERNAL_TEAM' : 'COURIER',
                    'shipping_company_name' => $data['shipping_method'] ?? null,
                    'shipping_tracking_number' => $data['tracking_number'] ?? null,
                    'delivery_city' => $data['shipping_city'] ?? ($data['city'] ?? null),
                    'delivery_district' => $data['district'] ?? null,
                    'delivery_address' => $data['shipping_address'] ?? null,
                    'delivery_map_url' => $data['map_url'] ?? null,
                    'order_date' => $data['order_date'] ?? now()->toDateString(),
                    'commercial_notes' => $data['customer_notes'] ?? null,
                    'production_notes' => $hasIncompleteManufactured ? 'بانتظار إضافة المنتجات ذات الخيارات والأسرّة يدويًا من خدمة العملاء.' : null,
                ], $linesData, $confirmer);

                if (! $hasIncompleteManufactured) {
                    $order->update(['status' => 'APPROVED_FOR_FULFILLMENT']);
                }

                if (! $hasIncompleteManufactured) {
                    $this->maybeRecordPayment($order, $customer, $data, $confirmer);
                }

                $row->update(['outcome' => 'CREATED', 'created_customer_order_id' => $order->id]);
            });
        } catch (Exception $e) {
            $row->update(['outcome' => 'FAILED', 'message' => $e->getMessage()]);
        }
    }

    protected function resolveOrCreateCustomer(SalesImportBatch $batch, array $data): Customer
    {
        $mobile = $data['customer_mobile'] ?? null;

        if ($mobile) {
            $existing = Customer::where('mobile', $mobile)->first();
            if ($existing) {
                return $existing;
            }
        }

        $customerType = CustomerType::where('code', 'SADIR_STORE')->first();

        return Customer::create([
            'customer_code' => DocumentNumberService::generateCustomerCode(),
            'customer_type_id' => $customerType?->id,
            'default_sales_channel_id' => $batch->sales_channel_id,
            'name' => $data['customer_name'] ?? 'عميل سلة',
            'mobile' => $mobile,
            'city' => $data['shipping_city'] ?? ($data['city'] ?? null),
            'address' => $data['shipping_address'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, array{target_type: string|null, mapping: SalesItemMapping|null}>  $resolutions
     * @return array<int, array<string, mixed>>
     */
    protected function buildLinesData(array $lines, array $resolutions): array
    {
        $linesData = [];

        foreach ($lines as $i => $line) {
            $resolution = $resolutions[$i];
            $targetType = $resolution['target_type'];
            $mapping = $resolution['mapping'];
            if ($targetType !== 'READY_STOCK' || ! $mapping?->material_id) {
                continue;
            }
            $qty = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['line_discount'] ?? 0);

            $common = [
                'external_product_name' => $line['product_name'] ?? null,
                'external_sku' => $line['sku'] ?? null,
                'external_line_reference' => $line['external_line_reference'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
            ];

            $linesData[] = array_merge($common, [
                'line_type' => 'READY_STOCK',
                'material_id' => $mapping->material_id,
            ]);
        }

        return $linesData;
    }

    protected function maybeRecordPayment(CustomerOrder $order, Customer $customer, array $data, User $confirmer): void
    {
        $status = mb_strtolower(trim((string) ($data['payment_status'] ?? '')));
        if (! in_array($status, self::PAID_STATUSES, true)) {
            return;
        }

        $payment = $this->paymentService->createPayment([
            'customer_id' => $customer->id,
            'payment_date' => $data['order_date'] ?? now()->toDateString(),
            'amount' => $order->total_amount,
            'payment_method' => 'ONLINE_STORE',
            'reference_number' => $data['payment_reference'] ?? null,
            'status' => 'CONFIRMED',
        ], $confirmer);

        if ((float) $order->total_amount > 0) {
            $this->allocationService->allocatePayment($payment, $order, (float) $order->total_amount, 'FINAL_PAYMENT', $confirmer, 'تخصيص تلقائي لدفعة طلب سلة مدفوعة إلكترونياً.');
        }
    }
}
