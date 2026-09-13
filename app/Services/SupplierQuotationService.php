<?php

namespace App\Services;

use App\Models\Material;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRfq;
use App\Models\PurchaseRfqSupplier;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationLine;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class SupplierQuotationService
{
    /**
     * Create an RFQ for candidate suppliers.
     */
    public function createRfq(array $data, User $creator): PurchaseRfq
    {
        return DB::transaction(function () use ($data, $creator) {
            $purchaseRequest = isset($data['purchase_request_id'])
                ? PurchaseRequest::find($data['purchase_request_id'])
                : null;

            $rfq = PurchaseRfq::create([
                'rfq_number' => DocumentNumberService::generateRfqNumber(),
                'purchase_request_id' => $purchaseRequest?->id,
                'issue_date' => $data['issue_date'] ?? now(),
                'response_due_date' => $data['response_due_date'] ?? null,
                'status' => 'SENT',
                'created_by_user_id' => $creator->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['supplier_ids'] as $supplierId) {
                PurchaseRfqSupplier::create([
                    'purchase_rfq_id' => $rfq->id,
                    'supplier_id' => $supplierId,
                    'status' => 'PENDING',
                ]);
            }

            return $rfq->load('rfqSuppliers.supplier', 'purchaseRequest');
        });
    }

    /**
     * Record a supplier quotation response.
     */
    public function recordQuotation(array $data, User $creator): SupplierQuotation
    {
        return DB::transaction(function () use ($data, $creator) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $rfq = isset($data['purchase_rfq_id']) ? PurchaseRfq::find($data['purchase_rfq_id']) : null;

            $subtotal = 0.0;
            $linesData = [];

            foreach ($data['lines'] as $lineData) {
                $material = Material::findOrFail($lineData['material_id']);
                $quotedQty = (float) $lineData['quoted_quantity'];
                $unitPrice = (float) $lineData['unit_price'];
                $conversionFactor = (float) ($lineData['conversion_factor'] ?? 1.0);

                if ($conversionFactor <= 0) {
                    throw new Exception('معامل التحويل يجب أن يكون أكبر من الصفر.');
                }

                $normalizedQty = round($quotedQty * $conversionFactor, 4);
                $lineTotal = round($quotedQty * $unitPrice, 4);
                $subtotal += $lineTotal;

                $linesData[] = [
                    'purchase_request_line_id' => $lineData['purchase_request_line_id'] ?? null,
                    'material_id' => $material->id,
                    'fabric_color_id' => $lineData['fabric_color_id'] ?? null,
                    'quoted_quantity' => $quotedQty,
                    'purchase_unit_id' => $lineData['purchase_unit_id'] ?? $material->purchase_unit_id ?? $material->base_unit_id,
                    'conversion_factor' => $conversionFactor,
                    'normalized_base_quantity' => $normalizedQty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'supplier_material_code' => $lineData['supplier_material_code'] ?? null,
                    'lead_time_days' => $lineData['lead_time_days'] ?? null,
                    'notes' => $lineData['notes'] ?? null,
                ];
            }

            $discount = (float) ($data['discount_amount'] ?? 0);
            $shipping = (float) ($data['shipping_amount'] ?? 0);
            $other = (float) ($data['other_charges'] ?? 0);
            $totalAmount = max(0.0, round($subtotal - $discount + $shipping + $other, 4));

            $quotation = SupplierQuotation::create([
                'supplier_quotation_number' => DocumentNumberService::generateSupplierQuotationNumber(),
                'purchase_rfq_id' => $rfq?->id,
                'supplier_id' => $supplier->id,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? now(),
                'valid_until' => $data['valid_until'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'payment_terms' => $data['payment_terms'] ?? null,
                'delivery_terms' => $data['delivery_terms'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'status' => 'SUBMITTED',
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'shipping_amount' => $shipping,
                'other_charges' => $other,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            foreach ($linesData as $line) {
                $line['supplier_quotation_id'] = $quotation->id;
                SupplierQuotationLine::create($line);
            }

            if ($rfq) {
                PurchaseRfqSupplier::where('purchase_rfq_id', $rfq->id)
                    ->where('supplier_id', $supplier->id)
                    ->update(['status' => 'RESPONDED']);

                $rfq->update(['status' => 'RESPONSES_RECEIVED']);
            }

            return $quotation->load('lines.material', 'lines.fabricColor', 'supplier');
        });
    }

    /**
     * Select a supplier quotation as commercial base.
     */
    public function selectQuotation(SupplierQuotation $quotation, User $user): SupplierQuotation
    {
        return DB::transaction(function () use ($quotation, $user) {
            $quotation->update([
                'status' => 'SELECTED',
                'selected_by_user_id' => $user->id,
                'selected_at' => now(),
            ]);

            return $quotation;
        });
    }
}
