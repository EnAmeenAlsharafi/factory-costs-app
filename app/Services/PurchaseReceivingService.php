<?php

namespace App\Services;

use App\Models\MaterialReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseReceivingService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Create a Stage 5 Draft MaterialReceipt pre-filled from an approved Purchase Order.
     */
    public function createReceiptFromPurchaseOrder(PurchaseOrder $po, User $user, array $input = []): MaterialReceipt
    {
        return $this->createDraftReceiptFromPO($po, $user, $input);
    }

    public function createDraftReceiptFromPO(PurchaseOrder $po, User $user, array $input = []): MaterialReceipt
    {
        if (! in_array($po->status, ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'], true)) {
            throw new Exception('لا يمكن إنشاء سند استلام لأمر شراء غير معتمد أو مغلق.');
        }

        return DB::transaction(function () use ($po, $user, $input) {
            $po->load('lines.material', 'lines.fabricColor', 'lines.purchaseUnit');

            $receiptLines = [];
            foreach ($po->lines as $poLine) {
                $remainingBase = $poLine->remaining_base_quantity;
                if ($remainingBase <= 0) {
                    continue; // Skip lines already fully received
                }

                $factor = (float) $poLine->conversion_factor;
                $remainingPurchaseQty = $factor > 0 ? round($remainingBase / $factor, 4) : $remainingBase;

                // Allow user overrides if supplied in input, default to remaining
                $qtyToReceive = isset($input['lines'][$poLine->id]['quantity_received'])
                    ? (float) $input['lines'][$poLine->id]['quantity_received']
                    : $remainingPurchaseQty;

                if ($qtyToReceive <= 0) {
                    continue;
                }

                $unitCostPurchase = isset($input['lines'][$poLine->id]['unit_cost_purchase'])
                    ? (float) $input['lines'][$poLine->id]['unit_cost_purchase']
                    : (float) $poLine->unit_price;

                $poColorCode = $poLine->fabric_color_code ?? $poLine->fabricColor?->color_code;

                $receiptLines[] = [
                    'purchase_order_line_id' => $poLine->id,
                    'material_id' => $poLine->material_id,
                    'fabric_color_id' => $poLine->fabric_color_id,
                    'fabric_color_code' => $poColorCode,
                    'quantity_received' => $qtyToReceive,
                    'purchase_unit_id' => $poLine->purchase_unit_id,
                    'conversion_factor' => $factor,
                    'unit_cost_purchase' => $unitCostPurchase,
                    'supplier_material_code' => $poLine->material->code,
                    'quality_note' => $input['lines'][$poLine->id]['quality_note'] ?? 'سليم بحالة جيدة',
                    'notes' => "مستلمة بموجب أمر الشراء {$po->purchase_order_number}",
                ];
            }

            if (empty($receiptLines)) {
                throw new Exception('لا توجد متبقيات خامات قابلة للاستلام بأمر الشراء هذا.');
            }

            return $this->inventoryService->createReceipt([
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $po->warehouse_id,
                'receipt_date' => $input['receipt_date'] ?? now()->format('Y-m-d'),
                'supplier_reference' => $input['supplier_reference'] ?? $po->supplier_reference,
                'purchase_invoice_reference' => $input['purchase_invoice_reference'] ?? null,
                'notes' => $input['notes'] ?? "استلام خامات لأمر الشراء رقم {$po->purchase_order_number}",
                'lines' => $receiptLines,
            ], $user);
        });
    }

    /**
     * Post a MaterialReceipt that is linked to a Purchase Order.
     * Enforces over-receipt ceiling, updates PO lines received_base_quantity, and updates PO status.
     */
    public function postLinkedReceipt(MaterialReceipt $receipt, User $user): MaterialReceipt
    {
        return DB::transaction(function () use ($receipt, $user) {
            $receipt->load(['lines.purchaseOrderLine.fabricColor', 'purchaseOrder']);

            // 1. Transactional ceiling enforcement and fabric color matching
            if ($receipt->purchase_order_id && $receipt->purchaseOrder) {
                $po = PurchaseOrder::where('id', $receipt->purchase_order_id)->lockForUpdate()->firstOrFail();

                if (in_array($po->status, ['CLOSED', 'CANCELLED'], true)) {
                    throw new Exception('لا يمكن ترحيل استلام لأمر شراء مغلق أو ملغى.');
                }

                foreach ($receipt->lines as $receiptLine) {
                    if ($receiptLine->purchase_order_line_id) {
                        $poLine = PurchaseOrderLine::where('id', $receiptLine->purchase_order_line_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        // Verification: Supplier, Material, Fabric color compatibility
                        if ($poLine->purchase_order_id !== $po->id) {
                            throw new Exception('بند الاستلام ينتمي لأمر شراء مختلف عن السند.');
                        }
                        if ($poLine->material_id !== $receiptLine->material_id) {
                            throw new Exception('مادة بند الاستلام لا تطابق المادة المحددة بأمر الشراء.');
                        }
                        if ($poLine->fabric_color_id && $receiptLine->fabric_color_id && $poLine->fabric_color_id !== $receiptLine->fabric_color_id) {
                            throw new Exception('لون القماش المحدد بسند الاستلام لا يطابق لون القماش بأمر الشراء.');
                        }
                        $poColor = $poLine->fabric_color_code ?? $poLine->fabricColor?->color_code;
                        $rcptColor = $receiptLine->fabric_color_code ?? $receiptLine->fabricColor?->color_code;
                        if (! empty($poColor) && ! empty($rcptColor) && $poColor !== $rcptColor) {
                            throw new Exception('لون القماش المحدد بسند الاستلام لا يطابق لون القماش بأمر الشراء.');
                        }

                        $newBaseQty = (float) $receiptLine->base_quantity;
                        $cumulativeReceived = (float) $poLine->received_base_quantity + $newBaseQty;

                        if ($cumulativeReceived > ((float) $poLine->ordered_base_quantity + 0.0001)) {
                            $maxAllowedBase = max(0.0, (float) $poLine->ordered_base_quantity - (float) $poLine->received_base_quantity);
                            throw new Exception("الكمية المستلمة الفعلية تتجاوز الكمية المتبقية بأمر الشراء ({$maxAllowedBase}). التجاوز ممنوع بغير موافقة إدارية.");
                        }
                    }
                }
            }

            // 2. Post the Stage 5 MaterialReceipt (creates inventory movement & inventory lot)
            $postedReceipt = $this->inventoryService->postReceipt($receipt, $user);

            // 3. Update PO lines received quantity and PO status
            if ($receipt->purchase_order_id) {
                $po = PurchaseOrder::where('id', $receipt->purchase_order_id)->first();
                if ($po) {
                    foreach ($postedReceipt->lines as $receiptLine) {
                        if ($receiptLine->purchase_order_line_id) {
                            $poLine = PurchaseOrderLine::find($receiptLine->purchase_order_line_id);
                            if ($poLine) {
                                $poLine->increment('received_base_quantity', (float) $receiptLine->base_quantity);
                            }
                        }
                    }

                    // Re-calculate PO status from fresh database lines
                    $lines = $po->lines()->get();
                    $allReceived = true;
                    $anyReceived = false;

                    foreach ($lines as $poLine) {
                        if ((float) $poLine->received_base_quantity > 0) {
                            $anyReceived = true;
                        }
                        if ((float) $poLine->received_base_quantity < ((float) $poLine->ordered_base_quantity - 0.0001)) {
                            $allReceived = false;
                        }
                    }

                    if ($allReceived && $anyReceived) {
                        $po->update(['status' => 'RECEIVED']);
                    } elseif ($anyReceived) {
                        $po->update(['status' => 'PARTIALLY_RECEIVED']);
                    }
                }
            }

            return $postedReceipt;
        });
    }

    /**
     * Calculate price variance summary for a Purchase Order against its posted receipts.
     */
    public function getPoPriceVarianceSummary(PurchaseOrder $po): array
    {
        $po->load('lines.materialReceiptLines.receipt', 'lines.material', 'lines.fabricColor');

        $variances = [];
        $totalExpectedCost = 0.0;
        $totalActualCost = 0.0;

        foreach ($po->lines as $poLine) {
            $expectedBaseCost = (float) $poLine->base_unit_equivalent_price;
            $receivedLines = $poLine->materialReceiptLines->filter(fn ($mrl) => $mrl->receipt && $mrl->receipt->isPosted());

            $lineTotalReceivedBase = (float) $receivedLines->sum('base_quantity');
            $lineTotalActualCost = (float) $receivedLines->sum('total_cost');
            $lineTotalExpectedCost = round($lineTotalReceivedBase * $expectedBaseCost, 4);

            $lineVariance = round($lineTotalActualCost - $lineTotalExpectedCost, 4);

            $totalExpectedCost += $lineTotalExpectedCost;
            $totalActualCost += $lineTotalActualCost;

            $variances[] = [
                'po_line_id' => $poLine->id,
                'material_name' => $poLine->material->name_ar,
                'color_name' => $poLine->fabricColor?->color_name_ar,
                'ordered_quantity' => (float) $poLine->ordered_quantity,
                'received_base_quantity' => $lineTotalReceivedBase,
                'expected_base_unit_price' => $expectedBaseCost,
                'actual_average_base_unit_cost' => $lineTotalReceivedBase > 0 ? round($lineTotalActualCost / $lineTotalReceivedBase, 4) : $expectedBaseCost,
                'expected_total_cost' => $lineTotalExpectedCost,
                'actual_total_cost' => $lineTotalActualCost,
                'variance_amount' => $lineVariance,
            ];
        }

        return [
            'total_expected_cost' => round($totalExpectedCost, 4),
            'total_actual_cost' => round($totalActualCost, 4),
            'net_variance' => round($totalActualCost - $totalExpectedCost, 4),
            'lines' => $variances,
        ];
    }
}
