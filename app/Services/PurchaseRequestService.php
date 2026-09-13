<?php

namespace App\Services;

use App\Models\Material;
use App\Models\ProductionOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    /**
     * Create a new purchase request with lines.
     */
    public function createRequest(array $data, User $creator): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $creator) {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            $request = PurchaseRequest::create([
                'request_number' => DocumentNumberService::generatePurchaseRequestNumber(),
                'request_date' => $data['request_date'] ?? now(),
                'requested_by_user_id' => $creator->id,
                'department_id' => $data['department_id'] ?? null,
                'warehouse_id' => $warehouse->id,
                'source_type' => $data['source_type'] ?? 'MANUAL',
                'source_reference_id' => $data['source_reference_id'] ?? null,
                'priority' => $data['priority'] ?? 'NORMAL',
                'required_by_date' => $data['required_by_date'] ?? null,
                'status' => 'DRAFT',
                'justification' => $data['justification'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $lineData) {
                $material = Material::findOrFail($lineData['material_id']);
                $qty = (float) $lineData['requested_quantity'];
                $unitCost = isset($lineData['estimated_unit_cost']) ? (float) $lineData['estimated_unit_cost'] : null;
                $totalCost = $unitCost !== null ? round($qty * $unitCost, 4) : null;

                PurchaseRequestLine::create([
                    'purchase_request_id' => $request->id,
                    'material_id' => $material->id,
                    'fabric_color_id' => $lineData['fabric_color_id'] ?? null,
                    'requested_quantity' => $qty,
                    'base_unit_id' => $material->base_unit_id,
                    'preferred_purchase_unit_id' => $lineData['preferred_purchase_unit_id'] ?? $material->purchase_unit_id ?? $material->base_unit_id,
                    'required_by_date' => $lineData['required_by_date'] ?? $request->required_by_date,
                    'estimated_unit_cost' => $unitCost,
                    'estimated_total_cost' => $totalCost,
                    'preferred_supplier_id' => $lineData['preferred_supplier_id'] ?? null,
                    'justification' => $lineData['justification'] ?? null,
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            return $request->load('lines.material', 'lines.fabricColor', 'lines.baseUnit');
        });
    }

    /**
     * Submit purchase request for review.
     */
    public function submitRequest(PurchaseRequest $request, User $user): PurchaseRequest
    {
        if ($request->status !== 'DRAFT') {
            throw new Exception('لا يمكن تقديم طلب شراء ليس في حالة مسودة.');
        }

        if ($request->lines()->count() === 0) {
            throw new Exception('يلزم إضافة بند مواد واحد على الأقل في طلب الشراء.');
        }

        $request->update([
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        return $request;
    }

    /**
     * Approve or reject purchase request.
     */
    public function reviewRequest(PurchaseRequest $request, User $reviewer, string $action, ?string $reason = null): PurchaseRequest
    {
        if (! in_array($request->status, ['SUBMITTED', 'UNDER_REVIEW'], true)) {
            throw new Exception('طلب الشراء ليس في حالة انتظار المراجعة والاعتماد.');
        }

        if ($action === 'APPROVE') {
            $request->update([
                'status' => 'APPROVED',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'approved_by_user_id' => $reviewer->id,
                'approved_at' => now(),
            ]);
        } elseif ($action === 'REJECT') {
            if (empty($reason)) {
                throw new Exception('يلزم تحديد سبب رفض طلب الشراء.');
            }

            $request->update([
                'status' => 'REJECTED',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejected_by_user_id' => $reviewer->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
        }

        return $request;
    }

    /**
     * Create PR from low stock items.
     */
    public function createFromLowStock(array $items, User $user, int $warehouseId): PurchaseRequest
    {
        $lines = [];
        foreach ($items as $item) {
            $material = Material::findOrFail($item['material_id']);
            $lines[] = [
                'material_id' => $material->id,
                'fabric_color_id' => $item['fabric_color_id'] ?? null,
                'requested_quantity' => (float) $item['requested_quantity'],
                'preferred_purchase_unit_id' => $material->purchase_unit_id ?? $material->base_unit_id,
                'justification' => 'طلب شراء تلقائي نتيجة انخفاض رصيد المخزون عن حد الإعادة',
            ];
        }

        return $this->createRequest([
            'warehouse_id' => $warehouseId,
            'source_type' => 'LOW_STOCK',
            'priority' => 'URGENT',
            'justification' => 'توليد طلب شراء بناءً على مواد تحت حد النواقص وإعادة الطلب',
            'lines' => $lines,
        ], $user);
    }

    /**
     * Create PR from Production Order shortage.
     */
    public function createFromProductionShortage(ProductionOrder $po, array $shortageItems, User $user, int $warehouseId): PurchaseRequest
    {
        $lines = [];
        foreach ($shortageItems as $item) {
            $material = Material::findOrFail($item['material_id']);
            $lines[] = [
                'material_id' => $material->id,
                'fabric_color_id' => $item['fabric_color_id'] ?? null,
                'requested_quantity' => (float) $item['shortage_quantity'],
                'preferred_purchase_unit_id' => $material->purchase_unit_id ?? $material->base_unit_id,
                'justification' => "تغطية نقص خامات لأمر الإنتاج رقم {$po->production_order_number}",
            ];
        }

        return $this->createRequest([
            'warehouse_id' => $warehouseId,
            'source_type' => 'PRODUCTION_SHORTAGE',
            'source_reference_id' => $po->id,
            'priority' => 'CRITICAL',
            'justification' => "طلب شراء لتغطية شح خامات أمر التصنيع رقم {$po->production_order_number}",
            'lines' => $lines,
        ], $user);
    }
}
