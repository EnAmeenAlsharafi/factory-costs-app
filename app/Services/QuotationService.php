<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        protected DocumentNumberService $documentNumberService,
        protected CustomerOrderService $customerOrderService
    ) {}

    /**
     * Create a new commercial quotation.
     */
    public function createQuotation(array $data, array $linesData, User $creator): Quotation
    {
        return DB::transaction(function () use ($data, $linesData, $creator) {
            $number = $this->documentNumberService->generateQuotationNumber();

            $quotation = Quotation::create([
                'quotation_number' => $number,
                'customer_id' => $data['customer_id'],
                'sales_channel_id' => $data['sales_channel_id'],
                'quotation_date' => $data['quotation_date'] ?? now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(30)->toDateString(),
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'status' => 'DRAFT',
                'notes' => $data['notes'] ?? null,
                'commercial_notes' => $data['commercial_notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            $this->syncLines($quotation, $linesData);
            $quotation->recalculateTotals();

            return $quotation;
        });
    }

    /**
     * Update an existing DRAFT quotation.
     */
    public function updateQuotation(Quotation $quotation, array $data, array $linesData): Quotation
    {
        if ($quotation->status === 'CONVERTED') {
            throw new Exception('لا يمكن تعديل عرض سعر تم تحويله لطلب عميل مسبقاً.');
        }

        return DB::transaction(function () use ($quotation, $data, $linesData) {
            $quotation->update([
                'customer_id' => $data['customer_id'],
                'sales_channel_id' => $data['sales_channel_id'],
                'quotation_date' => $data['quotation_date'] ?? $quotation->quotation_date,
                'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
                'notes' => $data['notes'] ?? $quotation->notes,
                'commercial_notes' => $data['commercial_notes'] ?? $quotation->commercial_notes,
            ]);

            $quotation->lines()->delete();
            $this->syncLines($quotation, $linesData);
            $quotation->recalculateTotals();

            return $quotation;
        });
    }

    /**
     * Approve a quotation.
     */
    public function approveQuotation(Quotation $quotation, User $approver): Quotation
    {
        if (in_array($quotation->status, ['CONVERTED', 'CANCELLED'])) {
            throw new Exception('لا يمكن اعتماد عرض سعر ملغى أو محول مسبقاً.');
        }

        $quotation->update([
            'status' => 'APPROVED',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $quotation;
    }

    public function rejectQuotation(Quotation $quotation, User $user): Quotation
    {
        if (in_array($quotation->status, ['CONVERTED', 'CANCELLED'])) {
            throw new Exception('لا يمكن رفض عرض سعر ملغى أو محول مسبقاً.');
        }

        $quotation->update([
            'status' => 'REJECTED',
        ]);

        return $quotation;
    }

    /**
     * Convert an approved quotation into a Customer Order.
     */
    public function convertToOrder(Quotation $quotation, User $converter, array $overrides = []): CustomerOrder
    {
        if ($quotation->status !== 'APPROVED') {
            throw new Exception('يمكن فقط تحويل عروض الأسعار المعتمدة إلى طلبات عملاء.');
        }

        return DB::transaction(function () use ($quotation, $converter, $overrides) {
            $orderNumber = $this->documentNumberService->generateOrderNumber();

            $order = CustomerOrder::create([
                'order_number' => $orderNumber,
                'quotation_id' => $quotation->id,
                'customer_id' => $quotation->customer_id,
                'sales_channel_id' => $quotation->sales_channel_id,
                'customer_reference' => $overrides['customer_reference'] ?? null,
                'external_order_reference' => $overrides['external_order_reference'] ?? null,
                'order_date' => now()->toDateString(),
                'requested_delivery_date' => $overrides['requested_delivery_date'] ?? now()->addDays(14)->toDateString(),
                'priority' => $overrides['priority'] ?? 'NORMAL',
                'status' => 'DRAFT',
                'subtotal' => $quotation->subtotal,
                'discount_total' => $quotation->discount_total,
                'total_amount' => $quotation->total_amount,
                'commercial_notes' => $quotation->commercial_notes,
                'production_notes' => $quotation->notes,
                'created_by_user_id' => $converter->id,
            ]);

            foreach ($quotation->lines as $qLine) {
                CustomerOrderLine::create([
                    'customer_order_id' => $order->id,
                    'product_model_id' => $qLine->product_model_id,
                    'product_configuration_id' => $qLine->product_configuration_id,
                    'customer_product_alias_id' => $qLine->customer_product_alias_id,
                    'custom_design' => $qLine->custom_design,
                    'custom_design_name' => $qLine->custom_design_name,
                    'requested_width_cm' => $qLine->requested_width_cm,
                    'requested_length_cm' => $qLine->requested_length_cm,
                    'reference_width_cm' => $qLine->reference_width_cm ?: $qLine->requested_width_cm,
                    'reference_length_cm' => $qLine->reference_length_cm ?: $qLine->requested_length_cm,
                    'has_storage' => $qLine->has_storage,
                    'fabric_supplier_id' => $qLine->fabric_supplier_id,
                    'fabric_material_id' => $qLine->fabric_material_id,
                    'fabric_color_id' => $qLine->fabric_color_id,
                    'fabric_color_code' => $qLine->fabric_color_code,
                    'quantity' => $qLine->quantity,
                    'unit_price' => $qLine->unit_price,
                    'discount_amount' => $qLine->discount_amount,
                    'line_total' => $qLine->line_total,
                    'notes' => $qLine->notes,
                    'production_notes' => $qLine->production_notes,
                ]);
            }

            $quotation->update([
                'status' => 'CONVERTED',
                'converted_to_order_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Helper to sync lines for a quotation.
     */
    protected function syncLines(Quotation $quotation, array $linesData): void
    {
        foreach ($linesData as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);
            $lineTotal = ($qty * $unitPrice) - $discount;

            QuotationLine::create([
                'quotation_id' => $quotation->id,
                'product_model_id' => ! empty($line['custom_design']) ? null : ($line['product_model_id'] ?? null),
                'product_configuration_id' => ! empty($line['custom_design']) ? null : ($line['product_configuration_id'] ?? null),
                'customer_product_alias_id' => $line['customer_product_alias_id'] ?? null,
                'custom_design' => ! empty($line['custom_design']),
                'custom_design_name' => $line['custom_design_name'] ?? null,
                'requested_width_cm' => $line['requested_width_cm'],
                'requested_length_cm' => $line['requested_length_cm'],
                'reference_width_cm' => $line['reference_width_cm'] ?? $line['requested_width_cm'],
                'reference_length_cm' => $line['reference_length_cm'] ?? $line['requested_length_cm'],
                'has_storage' => ! empty($line['has_storage']),
                'fabric_supplier_id' => $line['fabric_supplier_id'] ?? null,
                'fabric_material_id' => $line['fabric_material_id'] ?? null,
                'fabric_color_id' => $line['fabric_color_id'] ?? null,
                'fabric_color_code' => $line['fabric_color_code'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => max(0, $lineTotal),
                'notes' => $line['notes'] ?? null,
                'production_notes' => $line['production_notes'] ?? null,
            ]);
        }
    }
}
