<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderChange;
use App\Models\CustomerOrderLine;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CustomerOrderService
{
    public function __construct(protected DocumentNumberService $documentNumberService) {}

    /**
     * Create a new customer order.
     */
    public function createOrder(array $data, array $linesData, User $creator): CustomerOrder
    {
        return DB::transaction(function () use ($data, $linesData, $creator) {
            $number = $this->documentNumberService->generateOrderNumber();

            $order = CustomerOrder::create([
                'order_number' => $number,
                'quotation_id' => $data['quotation_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'sales_channel_id' => $data['sales_channel_id'],
                'customer_reference' => $data['customer_reference'] ?? null,
                'external_order_reference' => $data['external_order_reference'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'priority' => $data['priority'] ?? 'NORMAL',
                'status' => 'DRAFT',
                'commercial_notes' => $data['commercial_notes'] ?? null,
                'production_notes' => $data['production_notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            $this->syncLines($order, $linesData);
            $order->recalculateTotals();

            return $order;
        });
    }

    /**
     * Update customer order with audit logging and post-approval reset.
     */
    public function updateOrder(CustomerOrder $order, array $data, array $linesData, User $updater): CustomerOrder
    {
        if ($order->status === 'CANCELLED') {
            throw new Exception('لا يمكن تعديل طلب عميل ملغى.');
        }

        return DB::transaction(function () use ($order, $data, $linesData, $updater) {
            $wasApproved = $order->status === 'APPROVED_FOR_PRODUCTION';

            // Log header changes if any
            if (isset($data['priority']) && $data['priority'] !== $order->priority) {
                $this->recordOrderChange($order, null, 'priority', $order->priority, $data['priority'], $updater, $wasApproved);
            }
            if (isset($data['requested_delivery_date']) && $data['requested_delivery_date'] !== $order->requested_delivery_date?->toDateString()) {
                $this->recordOrderChange($order, null, 'requested_delivery_date', $order->requested_delivery_date?->toDateString(), $data['requested_delivery_date'], $updater, $wasApproved);
            }

            $order->update([
                'customer_id' => $data['customer_id'] ?? $order->customer_id,
                'sales_channel_id' => $data['sales_channel_id'] ?? $order->sales_channel_id,
                'customer_reference' => $data['customer_reference'] ?? $order->customer_reference,
                'external_order_reference' => $data['external_order_reference'] ?? $order->external_order_reference,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'requested_delivery_date' => $data['requested_delivery_date'] ?? $order->requested_delivery_date,
                'priority' => $data['priority'] ?? $order->priority,
                'commercial_notes' => $data['commercial_notes'] ?? $order->commercial_notes,
                'production_notes' => $data['production_notes'] ?? $order->production_notes,
            ]);

            // Audit & update lines
            $oldLines = $order->lines->keyBy('id');
            $order->lines()->delete();

            foreach ($linesData as $line) {
                $qty = (float) ($line['quantity'] ?? 1);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $discount = (float) ($line['discount_amount'] ?? 0);
                $lineTotal = max(0, ($qty * $unitPrice) - $discount);

                $newLine = CustomerOrderLine::create([
                    'customer_order_id' => $order->id,
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
                    'fabric_material_id' => $line['fabric_material_id'] ?? null,
                    'fabric_color_id' => $line['fabric_color_id'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'line_total' => $lineTotal,
                    'notes' => $line['notes'] ?? null,
                    'production_notes' => $line['production_notes'] ?? null,
                ]);

                // Check line change against old lines if present
                if (isset($line['id']) && isset($oldLines[$line['id']])) {
                    $old = $oldLines[$line['id']];
                    if ((float) $old->requested_width_cm != (float) $newLine->requested_width_cm || (float) $old->requested_length_cm != (float) $newLine->requested_length_cm) {
                        $this->recordOrderChange($order, $newLine->id, 'dimensions', "{$old->requested_width_cm}x{$old->requested_length_cm}", "{$newLine->requested_width_cm}x{$newLine->requested_length_cm}", $updater, $wasApproved);
                    }
                    if ($old->fabric_material_id != $newLine->fabric_material_id || $old->fabric_color_id != $newLine->fabric_color_id) {
                        $this->recordOrderChange($order, $newLine->id, 'fabric_and_color', "Material {$old->fabric_material_id} Color {$old->fabric_color_id}", "Material {$newLine->fabric_material_id} Color {$newLine->fabric_color_id}", $updater, $wasApproved);
                    }
                    if ((float) $old->quantity != (float) $newLine->quantity) {
                        $this->recordOrderChange($order, $newLine->id, 'quantity', (string) $old->quantity, (string) $newLine->quantity, $updater, $wasApproved);
                    }
                } else {
                    $this->recordOrderChange($order, $newLine->id, 'line_added', null, $newLine->display_name, $updater, $wasApproved);
                }
            }

            $order->recalculateTotals();

            // If changes occurred after production approval, reset status for re-review
            if ($wasApproved) {
                $order->update([
                    'status' => 'PENDING_PRODUCTION_REVIEW',
                    'production_notes' => trim(($order->production_notes ?? '').' [تم تعديل الطلب بعد اعتماد التصنيع وتتطلب إعادة المراجعة والاعتماد]'),
                ]);
            }

            return $order;
        });
    }

    /**
     * Submit order for production review.
     */
    public function submitForProductionReview(CustomerOrder $order): CustomerOrder
    {
        if ($order->status === 'CANCELLED') {
            throw new Exception('لا يمكن إرسال طلب ملغى للمراجعة.');
        }

        $order->update([
            'status' => 'PENDING_PRODUCTION_REVIEW',
        ]);

        return $order;
    }

    public function cancelOrder(CustomerOrder $order): CustomerOrder
    {
        $order->update([
            'status' => 'CANCELLED',
        ]);

        return $order;
    }

    /**
     * Approve order for production by Production Manager / Admin.
     */
    public function approveForProduction(CustomerOrder $order, User $approver, array $lineReferenceDimensions = []): CustomerOrder
    {
        if ($order->status === 'CANCELLED') {
            throw new Exception('لا يمكن اعتماد طلب ملغى للتصنيع.');
        }

        return DB::transaction(function () use ($order, $approver, $lineReferenceDimensions) {
            // Update line reference dimensions if provided during review
            foreach ($lineReferenceDimensions as $lineId => $dims) {
                $line = CustomerOrderLine::where('customer_order_id', $order->id)->where('id', $lineId)->first();
                if ($line) {
                    $line->update([
                        'reference_width_cm' => $dims['reference_width_cm'] ?? $line->reference_width_cm,
                        'reference_length_cm' => $dims['reference_length_cm'] ?? $line->reference_length_cm,
                        'production_notes' => $dims['production_notes'] ?? $line->production_notes,
                    ]);
                }
            }

            $order->update([
                'status' => 'APPROVED_FOR_PRODUCTION',
                'production_reviewed_by_user_id' => $approver->id,
                'production_reviewed_at' => now(),
                'production_approved_by_user_id' => $approver->id,
                'production_approved_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Audit log an order change.
     */
    public function recordOrderChange(CustomerOrder $order, ?int $lineId, string $fieldName, mixed $oldVal, mixed $newVal, User $user, bool $wasApproved = false, ?string $notes = null): CustomerOrderChange
    {
        return CustomerOrderChange::create([
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $lineId,
            'field_name' => $fieldName,
            'old_value' => is_array($oldVal) ? json_encode($oldVal) : (string) $oldVal,
            'new_value' => is_array($newVal) ? json_encode($newVal) : (string) $newVal,
            'change_type' => $lineId ? 'LINE_UPDATE' : 'HEADER_UPDATE',
            'requested_by_user_id' => $user->id,
            'occurred_after_production_approval' => $wasApproved,
            'notes' => $notes ?? ($wasApproved ? 'تعديل تم بعد اعتماد الطلب للتصنيع' : 'تحديث بيانات الطلب'),
        ]);
    }

    /**
     * Sync lines for customer order.
     */
    protected function syncLines(CustomerOrder $order, array $linesData): void
    {
        foreach ($linesData as $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);
            $lineTotal = max(0, ($qty * $unitPrice) - $discount);

            CustomerOrderLine::create([
                'customer_order_id' => $order->id,
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
                'fabric_material_id' => $line['fabric_material_id'] ?? null,
                'fabric_color_id' => $line['fabric_color_id'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
                'notes' => $line['notes'] ?? null,
                'production_notes' => $line['production_notes'] ?? null,
            ]);
        }
    }
}
