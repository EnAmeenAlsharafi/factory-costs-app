<?php

namespace App\Services;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Create a purchase order from request lines or direct entry.
     */
    public function createOrder(array $data, User $creator): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $creator) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $purchaseRequest = isset($data['purchase_request_id']) ? PurchaseRequest::find($data['purchase_request_id']) : null;
            $supplierQuotation = isset($data['supplier_quotation_id']) ? SupplierQuotation::find($data['supplier_quotation_id']) : null;

            $subtotal = 0.0;
            $linesToCreate = [];

            foreach ($data['lines'] as $lineData) {
                $material = Material::findOrFail($lineData['material_id']);
                $orderedQty = (float) $lineData['ordered_quantity'];
                $unitPrice = (float) $lineData['unit_price'];
                $conversionFactor = (float) ($lineData['conversion_factor'] ?? 1.0);

                if ($conversionFactor <= 0) {
                    throw new Exception('معامل التحويل لأمر الشراء يجب أن يكون أكبر من الصفر.');
                }

                $orderedBaseQty = round($orderedQty * $conversionFactor, 4);

                // Transactional over-allocation check against PR line if linked
                if (! empty($lineData['purchase_request_line_id'])) {
                    $prLine = PurchaseRequestLine::where('id', $lineData['purchase_request_line_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($prLine) {
                        $alreadyOrderedBase = (float) PurchaseOrderLine::where('purchase_request_line_id', $prLine->id)
                            ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['CANCELLED']))
                            ->sum('ordered_base_quantity');

                        $remainingPRBase = max(0.0, (float) $prLine->requested_quantity - $alreadyOrderedBase);
                        if ($orderedBaseQty > ($remainingPRBase + 0.0001)) {
                            throw new Exception("الكمية المطلوبة في أمر الشراء ({$orderedBaseQty}) تتجاوز الكمية المتاحة بطلب الشراء المعتمد ({$remainingPRBase}).");
                        }
                    }
                }

                $lineTotal = round($orderedQty * $unitPrice, 4);
                $subtotal += $lineTotal;

                $linesToCreate[] = [
                    'purchase_request_line_id' => $lineData['purchase_request_line_id'] ?? null,
                    'supplier_quotation_line_id' => $lineData['supplier_quotation_line_id'] ?? null,
                    'material_id' => $material->id,
                    'fabric_color_id' => $lineData['fabric_color_id'] ?? null,
                    'ordered_quantity' => $orderedQty,
                    'purchase_unit_id' => $lineData['purchase_unit_id'] ?? $material->purchase_unit_id ?? $material->base_unit_id,
                    'conversion_factor' => $conversionFactor,
                    'ordered_base_quantity' => $orderedBaseQty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'notes' => $lineData['notes'] ?? null,
                ];
            }

            $discount = (float) ($data['discount_amount'] ?? 0);
            $shipping = (float) ($data['shipping_amount'] ?? 0);
            $other = (float) ($data['other_charges'] ?? 0);
            $totalAmount = max(0.0, round($subtotal - $discount + $shipping + $other, 4));

            $po = PurchaseOrder::create([
                'purchase_order_number' => DocumentNumberService::generatePurchaseOrderNumber(),
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $purchaseRequest?->id,
                'supplier_quotation_id' => $supplierQuotation?->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'status' => 'DRAFT',
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'shipping_amount' => $shipping,
                'other_charges' => $other,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            foreach ($linesToCreate as $line) {
                $line['purchase_order_id'] = $po->id;
                PurchaseOrderLine::create($line);
            }

            // Update PR status if applicable
            if ($purchaseRequest) {
                $this->updatePurchaseRequestStatus($purchaseRequest);
            }

            return $po->load('lines.material', 'lines.fabricColor', 'supplier', 'warehouse');
        });
    }

    /**
     * Approve purchase order.
     */
    public function approveOrder(PurchaseOrder $order, User $approver): PurchaseOrder
    {
        if (! in_array($order->status, ['DRAFT', 'PENDING_APPROVAL'], true)) {
            throw new Exception('أمر الشراء ليس في حالة مسودة أو قيد انتظار الاعتماد.');
        }

        $order->update([
            'status' => 'APPROVED',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $order;
    }

    /**
     * Mark PO as sent to supplier.
     */
    public function markSent(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if ($order->status !== 'APPROVED') {
            throw new Exception('يلزم اعتماد أمر الشراء أولاً قبل إرساله للمورد.');
        }

        $order->update([
            'status' => 'SENT',
            'sent_at' => now(),
        ]);

        return $order;
    }

    /**
     * Close PO short (accept unfulfilled remainder as closed).
     */
    public function closeOrder(PurchaseOrder $order, User $user, string $reason): PurchaseOrder
    {
        if (in_array($order->status, ['CLOSED', 'CANCELLED'], true)) {
            throw new Exception('أمر الشراء مغلق أو ملغى بالفعل.');
        }

        if (empty($reason)) {
            throw new Exception('يلزم تحديد سبب إغلاق أمر الشراء.');
        }

        $order->update([
            'status' => 'CLOSED',
            'close_reason' => $reason,
            'closed_at' => now(),
        ]);

        return $order;
    }

    /**
     * Cancel purchase order (if no receipt posted).
     */
    public function cancelOrder(PurchaseOrder $order, User $user, string $reason): PurchaseOrder
    {
        if ($order->status === 'CANCELLED') {
            throw new Exception('أمر الشراء ملغى بالفعل.');
        }

        $totalReceived = (float) $order->lines()->sum('received_base_quantity');
        if ($totalReceived > 0) {
            throw new Exception('لا يمكن إلغاء أمر شراء تم استلام حركات استلام مخزنية عليه جزئياً أو كلياً. استخدم خيار الإغلاق بدلاً من ذلك.');
        }

        $order->update([
            'status' => 'CANCELLED',
            'close_reason' => "إلغاء: {$reason}",
            'closed_at' => now(),
        ]);

        if ($order->purchase_request_id) {
            $this->updatePurchaseRequestStatus($order->purchaseRequest);
        }

        return $order;
    }

    /**
     * Helper to update PurchaseRequest status based on cumulative PO lines.
     */
    public function updatePurchaseRequestStatus(PurchaseRequest $pr): void
    {
        $pr->loadMissing('lines');
        $allOrdered = true;
        $anyOrdered = false;

        foreach ($pr->lines as $prLine) {
            $orderedBase = (float) PurchaseOrderLine::where('purchase_request_line_id', $prLine->id)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['CANCELLED']))
                ->sum('ordered_base_quantity');

            if ($orderedBase > 0) {
                $anyOrdered = true;
            }
            if ($orderedBase < ((float) $prLine->requested_quantity - 0.0001)) {
                $allOrdered = false;
            }
        }

        if ($allOrdered && $anyOrdered) {
            $pr->update(['status' => 'ORDERED']);
        } elseif ($anyOrdered) {
            $pr->update(['status' => 'PARTIALLY_ORDERED']);
        }
    }
}
