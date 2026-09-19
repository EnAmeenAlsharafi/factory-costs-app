<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\ProductionMaterialRequest;
use App\Models\ProductionMaterialRequestLine;
use App\Models\ProductionMaterialRequirement;
use App\Models\ProductionOrder;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class ProductionMaterialRequestService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Generate or refresh material requirements from order's active BOM recipe version.
     */
    public function createMaterialRequirementsFromRecipe(ProductionOrder $productionOrder): int
    {
        return DB::transaction(function () use ($productionOrder) {
            $recipeVersion = $productionOrder->recipeVersion;
            if (! $recipeVersion) {
                return 0;
            }

            // Remove existing requirements for clean sync if not yet requested
            $hasRequests = $productionOrder->materialRequests()->exists();
            if ($hasRequests) {
                // If requests exist, retain existing requirements to avoid dangling FKs
                return $productionOrder->materialRequirements()->count();
            }

            $productionOrder->materialRequirements()->delete();

            $recipeVersion->load('items.material.category', 'items.semiFinishedComponent', 'items.unit');

            $count = 0;
            foreach ($recipeVersion->items as $item) {
                $reqQtyPerUnit = (float) ($item->quantity_per_unit ?? $item->quantity);
                $wastePct = (float) $item->waste_percentage;
                $plannedQtyPerUnit = $reqQtyPerUnit * (1 + ($wastePct / 100));
                $totalPlannedQty = $plannedQtyPerUnit * $productionOrder->ordered_quantity;

                $materialId = $item->material_id;
                $materialName = $item->material?->name_ar
                    ?? $item->semiFinishedComponent?->name_ar
                    ?? $item->notes;

                // Resolve fabric requirement to customer's order-specified fabric if item is fabric category or fabric material
                if ($item->material && (strtoupper($item->material->category?->code ?? '') === 'FABRIC' || str_contains($item->material->name_ar, 'قماش'))) {
                    if ($productionOrder->fabric_material_id) {
                        $materialId = $productionOrder->fabric_material_id;
                        $resolvedFabric = Material::find($productionOrder->fabric_material_id);
                        if ($resolvedFabric) {
                            $materialName = $resolvedFabric->name_ar;
                            if ($productionOrder->fabric_color_code) {
                                $materialName .= " (لون: {$productionOrder->fabric_color_code})";
                            }
                            if ($productionOrder->fabricSupplier) {
                                $materialName .= " [المورد: {$productionOrder->fabricSupplier->name}]";
                            }
                        }
                    }
                }

                ProductionMaterialRequirement::create([
                    'production_order_id' => $productionOrder->id,
                    'manufacturing_recipe_version_id' => $recipeVersion->id,
                    'manufacturing_recipe_item_id' => $item->id,
                    'material_id' => $materialId,
                    'semi_finished_component_id' => $item->semi_finished_component_id,
                    'required_quantity_per_unit' => $reqQtyPerUnit,
                    'waste_percentage' => $wastePct,
                    'planned_quantity_per_unit' => $plannedQtyPerUnit,
                    'production_quantity' => $productionOrder->ordered_quantity,
                    'total_planned_quantity' => $totalPlannedQty,
                    'unit_id' => $item->unit_id ?? $item->material?->base_unit_id,
                    'material_name_snapshot' => $materialName,
                    'notes' => $item->notes,
                ]);

                $count++;
            }

            return $count;
        });
    }

    /**
     * Create a new material request for a production order.
     */
    public function createRequest(ProductionOrder $productionOrder, User $user, array $data): ProductionMaterialRequest
    {
        return DB::transaction(function () use ($productionOrder, $user, $data) {
            $requestNumber = DocumentNumberService::generateRequestNumber();

            $request = ProductionMaterialRequest::create([
                'request_number' => $requestNumber,
                'production_order_id' => $productionOrder->id,
                'requested_by_user_id' => $user->id,
                'requested_from_department_id' => $data['requested_from_department_id'] ?? $user->department_id,
                'warehouse_id' => $data['warehouse_id'],
                'request_date' => $data['request_date'] ?? now()->toDateString(),
                'status' => 'DRAFT',
                'notes' => $data['notes'] ?? null,
            ]);

            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $lineData) {
                    $reqId = $lineData['production_material_requirement_id'] ?? null;
                    $req = $reqId ? ProductionMaterialRequirement::find($reqId) : null;
                    $materialId = $lineData['material_id'] ?? $req?->material_id;

                    if (! $materialId) {
                        throw new Exception('يلزم تحديد الخامة المطلوبة في السطر.');
                    }

                    $requestedQty = (float) $lineData['requested_quantity'];
                    $baseUnitId = $lineData['base_unit_id'] ?? $req?->unit_id;

                    $request->lines()->create([
                        'production_material_requirement_id' => $req?->id,
                        'material_id' => $materialId,
                        'fabric_color_id' => $lineData['fabric_color_id'] ?? ($productionOrder->fabric_color_id ?? null),
                        'fabric_color_code' => $lineData['fabric_color_code'] ?? ($productionOrder->fabric_color_code ?? null),
                        'requested_quantity' => $requestedQty,
                        'approved_quantity' => $lineData['approved_quantity'] ?? $requestedQty,
                        'issued_quantity' => 0,
                        'base_unit_id' => $baseUnitId,
                        'request_reason' => $lineData['request_reason'] ?? 'PLANNED_PRODUCTION',
                        'notes' => $lineData['notes'] ?? null,
                    ]);
                }
            }

            return $request;
        });
    }

    /**
     * Submit a draft material request.
     */
    public function submitRequest(ProductionMaterialRequest $request): ProductionMaterialRequest
    {
        if ($request->status !== 'DRAFT') {
            throw new Exception('يمكن فقط تقديم طلبات المواد في حالة المسودة.');
        }

        if ($request->lines()->count() === 0) {
            throw new Exception('لا يمكن تقديم طلب مواد لا يحتوي على بنود.');
        }

        $request->update(['status' => 'SUBMITTED']);

        return $request;
    }

    /**
     * Fulfill a submitted or partially fulfilled material request by creating and posting a MaterialIssue from raw materials warehouse.
     */
    public function fulfillRequest(ProductionMaterialRequest $request, User $issuer, array $lineFulfillments): MaterialIssue
    {
        return DB::transaction(function () use ($request, $issuer, $lineFulfillments) {
            $lockedRequest = ProductionMaterialRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedRequest->status, ['SUBMITTED', 'PARTIALLY_FULFILLED'], true)) {
                throw new Exception('يمكن فقط صرف الطلبات المقدمة أو المستوفاة جزئياً.');
            }

            if ($lineFulfillments === []) {
                throw new Exception('يلزم تحديد بند واحد على الأقل للصرف.');
            }

            $issueNumber = DocumentNumberService::generateIssueNumber();

            $issue = MaterialIssue::create([
                'issue_number' => $issueNumber,
                'warehouse_id' => $lockedRequest->warehouse_id,
                'department_id' => $lockedRequest->requested_from_department_id,
                'created_by_user_id' => $issuer->id,
                'issued_by_user_id' => $issuer->id,
                'issue_date' => now()->toDateString(),
                'notes' => "صرف مواد لطلب رقم {$lockedRequest->request_number} - أمر إنتاج رقم {$lockedRequest->productionOrder->production_order_number}",
                'status' => 'DRAFT',
                'production_order_id' => $lockedRequest->production_order_id,
                'production_material_request_id' => $lockedRequest->id,
            ]);

            foreach ($lineFulfillments as $fulfillment) {
                $lineId = $fulfillment['request_line_id'];
                $reqLine = ProductionMaterialRequestLine::query()
                    ->whereKey($lineId)
                    ->where('production_material_request_id', $lockedRequest->id)
                    ->lockForUpdate()
                    ->first();

                if (! $reqLine) {
                    throw new Exception('بند طلب المواد لا يتبع الطلب الجاري صرفه.');
                }

                $lotId = $fulfillment['inventory_lot_id'];
                $issueQty = (float) $fulfillment['quantity'];

                if ($issueQty <= 0) {
                    throw new Exception('يجب أن تكون كمية الصرف أكبر من صفر.');
                }

                $lot = InventoryLot::where('id', $lotId)->lockForUpdate()->firstOrFail();

                if ((int) $lot->warehouse_id !== (int) $lockedRequest->warehouse_id) {
                    throw new Exception('اللوت المحدد لا يتبع مستودع طلب المواد.');
                }

                if ((int) $lot->material_id !== (int) $reqLine->material_id) {
                    throw new Exception('خامة اللوت المحدد لا تطابق خامة بند الطلب.');
                }

                if ($reqLine->fabric_color_id !== null && $lot->fabric_color_id !== null && (int) $lot->fabric_color_id !== (int) $reqLine->fabric_color_id) {
                    throw new Exception('لون اللوت المحدد لا يطابق لون بند الطلب.');
                }

                $reqColor = $reqLine->fabric_color_code
                    ?? $lockedRequest->productionOrder?->fabric_color_code
                    ?? $reqLine->fabricColor?->color_code;
                $lotColor = $lot->fabric_color_code
                    ?? $lot->fabricColor?->color_code;

                if (! empty($reqColor) && ! empty($lotColor) && $lotColor !== $reqColor) {
                    throw new Exception('لون اللوت المحدد لا يطابق لون بند الطلب.');
                }

                $targetQty = (float) ($reqLine->approved_quantity ?? $reqLine->requested_quantity);
                $remainingRequestedQty = max(0, $targetQty - (float) $reqLine->issued_quantity);

                if ($issueQty > $remainingRequestedQty) {
                    throw new Exception("الكمية المطلوبة ({$issueQty}) تتجاوز الكمية المتبقية في بند الطلب ({$remainingRequestedQty}).");
                }

                if ($issueQty > $lot->remaining_quantity) {
                    throw new Exception("الكمية المطلوبة ({$issueQty}) تتجاوز رصيد اللوت المتاح ({$lot->remaining_quantity}).");
                }

                $issueLineCost = round($issueQty * $lot->unit_cost, 4);

                $issue->lines()->create([
                    'inventory_lot_id' => $lot->id,
                    'material_id' => $reqLine->material_id,
                    'fabric_color_id' => $reqLine->fabric_color_id ?? $lot->fabric_color_id,
                    'base_unit_id' => $reqLine->base_unit_id,
                    'requested_quantity' => $issueQty,
                    'issued_quantity' => $issueQty,
                    'unit_cost' => $lot->unit_cost,
                    'total_cost' => $issueLineCost,
                    'notes' => $fulfillment['notes'] ?? null,
                    'production_material_request_line_id' => $reqLine->id,
                    'request_reason' => $reqLine->request_reason,
                ]);

                // Increment issued quantity on request line
                $reqLine->issued_quantity += $issueQty;
                $reqLine->save();
            }

            // Post the inventory issue via InventoryService
            $this->inventoryService->postIssue($issue, $issuer);

            // Re-evaluate request status
            $allLinesFulfilled = true;
            foreach ($lockedRequest->lines()->get() as $line) {
                $targetQty = $line->approved_quantity ?? $line->requested_quantity;
                if ($line->issued_quantity < $targetQty) {
                    $allLinesFulfilled = false;
                    break;
                }
            }

            $lockedRequest->update([
                'status' => $allLinesFulfilled ? 'FULFILLED' : 'PARTIALLY_FULFILLED',
                'reviewed_by_user_id' => $issuer->id,
                'reviewed_at' => now(),
                'fulfilled_at' => $allLinesFulfilled ? now() : $lockedRequest->fulfilled_at,
            ]);

            return $issue;
        });
    }
}
