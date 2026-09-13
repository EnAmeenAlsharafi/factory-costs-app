<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplierQuotationLine;

class ProcurementReportingService
{
    /**
     * Get summary metrics for procurement dashboard.
     */
    public function getDashboardMetrics(): array
    {
        return [
            'pending_requests_count' => PurchaseRequest::whereIn('status', ['SUBMITTED', 'UNDER_REVIEW'])->count(),
            'approved_requests_count' => PurchaseRequest::where('status', 'APPROVED')->count(),
            'open_orders_count' => PurchaseOrder::whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])->count(),
            'late_orders_count' => PurchaseOrder::whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'])
                ->where('expected_delivery_date', '<', now()->startOfDay())
                ->count(),
        ];
    }

    /**
     * Get price history log for a specific material across supplier quotes, POs, and actual receipts.
     */
    public function getMaterialPriceHistory(Material $material): array
    {
        $quoteLines = SupplierQuotationLine::with('supplierQuotation.supplier')
            ->where('material_id', $material->id)
            ->latest()
            ->take(10)
            ->get();

        $receiptLines = MaterialReceiptLine::with('receipt.supplier')
            ->where('material_id', $material->id)
            ->whereHas('receipt', fn ($q) => $q->where('status', 'POSTED'))
            ->latest()
            ->take(10)
            ->get();

        $history = [];

        foreach ($receiptLines as $rl) {
            $history[] = [
                'type' => 'RECEIPT_ACTUAL',
                'date' => $rl->receipt->receipt_date->format('Y-m-d'),
                'supplier_name' => $rl->receipt->supplier?->name,
                'reference' => $rl->receipt->receipt_number,
                'unit_cost' => (float) $rl->unit_cost_base,
                'unit_name' => $rl->baseUnit?->name_ar,
                'notes' => 'سعر توريد فعلي بموجب سند استلام',
            ];
        }

        foreach ($quoteLines as $ql) {
            $history[] = [
                'type' => 'SUPPLIER_QUOTE',
                'date' => $ql->supplierQuotation->quotation_date->format('Y-m-d'),
                'supplier_name' => $ql->supplierQuotation->supplier?->name,
                'reference' => $ql->supplierQuotation->supplier_quotation_number,
                'unit_cost' => (float) $ql->base_unit_equivalent_price,
                'unit_name' => $ql->material->unitOfMeasure?->name_ar,
                'notes' => 'عرض سعر مورد',
            ];
        }

        usort($history, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $history;
    }
}
