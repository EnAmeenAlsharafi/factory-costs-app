<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\MaterialReceipt;
use App\Models\MaterialReturn;
use App\Models\MaterialReturnLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Post a draft material receipt atomically.
     */
    public function postReceipt(MaterialReceipt $receipt, User|int $user): MaterialReceipt
    {
        $userObj = is_numeric($user) ? User::findOrFail($user) : $user;

        if (! $receipt->isDraft()) {
            throw new Exception('لا يمكن اعتماد إيصال استلام غير مسودة.');
        }

        if ($receipt->lines()->count() === 0) {
            throw new Exception('لا يمكن اعتماد إيصال استلام لا يحتوي على بنود خامات.');
        }

        return DB::transaction(function () use ($receipt, $userObj) {
            foreach ($receipt->lines as $line) {
                // Enforce Fabric color policy
                if ($line->material->category?->code === 'FABRIC' && ! $line->fabric_color_id) {
                    throw new Exception("يلزم تحديد درجة اللون لمادة القماش: {$line->material->name_ar}");
                }

                // Create Inventory Lot
                $lotCode = DocumentNumberService::generateLotCode();
                $lot = InventoryLot::create([
                    'lot_code' => $lotCode,
                    'material_id' => $line->material_id,
                    'fabric_color_id' => $line->fabric_color_id,
                    'supplier_id' => $receipt->supplier_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'receipt_line_id' => $line->id,
                    'received_date' => $receipt->receipt_date,
                    'original_quantity' => $line->base_quantity,
                    'remaining_quantity' => $line->base_quantity,
                    'base_unit_id' => $line->base_unit_id,
                    'unit_cost' => $line->unit_cost_base,
                    'quality_grade' => $line->quality_note,
                    'supplier_lot_reference' => $line->lot_reference,
                    'status' => 'ACTIVE',
                    'notes' => $line->notes,
                ]);

                // Create RECEIPT Movement (IN)
                InventoryMovement::create([
                    'movement_number' => DocumentNumberService::generateMovementNumber(),
                    'movement_type' => 'RECEIPT',
                    'material_id' => $line->material_id,
                    'fabric_color_id' => $line->fabric_color_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'inventory_lot_id' => $lot->id,
                    'quantity' => $line->base_quantity,
                    'unit_id' => $line->base_unit_id,
                    'direction' => 'IN',
                    'unit_cost' => $line->unit_cost_base,
                    'total_cost' => $line->total_cost,
                    'reference_type' => MaterialReceipt::class,
                    'reference_id' => $receipt->id,
                    'occurred_at' => $receipt->receipt_date->startOfDay(),
                    'performed_by_user_id' => $userObj->id,
                    'notes' => "إيصال استلام رقم {$receipt->receipt_number}",
                ]);
            }

            $receipt->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'received_by_user_id' => $receipt->received_by_user_id ?? $userObj->id,
            ]);

            return $receipt;
        });
    }

    /**
     * Post a draft material issue atomically with row locking to prevent negative inventory.
     */
    public function postIssue(MaterialIssue $issue, User|int $user): MaterialIssue
    {
        $userObj = is_numeric($user) ? User::findOrFail($user) : $user;

        if (! $issue->isDraft()) {
            throw new Exception('لا يمكن اعتماد سند صرف غير مسودة.');
        }

        if ($issue->lines()->count() === 0) {
            throw new Exception('لا يمكن اعتماد سند صرف لا يحتوي على بنود خامات.');
        }

        return DB::transaction(function () use ($issue, $userObj) {
            foreach ($issue->lines as $line) {
                // Lock lot row to prevent concurrent race conditions
                $lot = InventoryLot::where('id', $line->inventory_lot_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($line->issued_quantity > $lot->remaining_quantity) {
                    throw new Exception("الكمية المطلوبة للصرف ({$line->issued_quantity}) تتجاوز الكمية المتاحة في اللوت {$lot->lot_code} ({$lot->remaining_quantity}).");
                }

                // Deduct cached remaining quantity
                $lot->remaining_quantity -= $line->issued_quantity;
                if ($lot->remaining_quantity <= 0) {
                    $lot->remaining_quantity = 0;
                    $lot->status = 'EXHAUSTED';
                }
                $lot->save();

                // Create ISSUE Movement (OUT)
                InventoryMovement::create([
                    'movement_number' => DocumentNumberService::generateMovementNumber(),
                    'movement_type' => 'ISSUE',
                    'material_id' => $line->material_id,
                    'fabric_color_id' => $line->fabric_color_id,
                    'warehouse_id' => $issue->warehouse_id,
                    'inventory_lot_id' => $lot->id,
                    'quantity' => $line->issued_quantity,
                    'unit_id' => $line->base_unit_id,
                    'direction' => 'OUT',
                    'unit_cost' => $lot->unit_cost,
                    'total_cost' => round($line->issued_quantity * $lot->unit_cost, 4),
                    'reference_type' => MaterialIssue::class,
                    'reference_id' => $issue->id,
                    'occurred_at' => $issue->issue_date->startOfDay(),
                    'performed_by_user_id' => $userObj->id,
                    'notes' => "سند صرف رقم {$issue->issue_number}",
                ]);
            }

            $issue->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'issued_by_user_id' => $issue->issued_by_user_id ?? $userObj->id,
            ]);

            return $issue;
        });
    }

    /**
     * Post a material return atomically.
     */
    public function postReturn(MaterialReturn $return, User|int $user): MaterialReturn
    {
        $userObj = is_numeric($user) ? User::findOrFail($user) : $user;

        if (! $return->isDraft()) {
            throw new Exception('لا يمكن اعتماد سند مرتجع غير مسودة.');
        }

        if ($return->lines()->count() === 0) {
            throw new Exception('لا يمكن اعتماد سند مرتجع لا يحتوي على بنود خامات.');
        }

        return DB::transaction(function () use ($return, $userObj) {
            foreach ($return->lines as $line) {
                $lot = InventoryLot::where('id', $line->inventory_lot_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Validate against original issue line if linked
                if ($line->original_issue_line_id) {
                    $issueLine = $line->originalIssueLine;
                    $alreadyReturned = MaterialReturnLine::where('original_issue_line_id', $issueLine->id)
                        ->whereHas('return', function ($q) {
                            $q->where('status', 'POSTED');
                        })
                        ->sum('returned_quantity');

                    if (($alreadyReturned + $line->returned_quantity) > $issueLine->issued_quantity) {
                        $maxReturnable = $issueLine->issued_quantity - $alreadyReturned;
                        throw new Exception("كمية المرتجع الإجمالية تتجاوز المسموح به. أقصى كمية يمكن إرجاعها من هذه الصرفة هي: {$maxReturnable}");
                    }
                }

                // Restore lot remaining quantity
                $lot->remaining_quantity += $line->returned_quantity;
                if ($lot->status === 'EXHAUSTED' && $lot->remaining_quantity > 0) {
                    $lot->status = 'ACTIVE';
                }
                $lot->save();

                // Create RETURN Movement (IN)
                InventoryMovement::create([
                    'movement_number' => DocumentNumberService::generateMovementNumber(),
                    'movement_type' => 'RETURN',
                    'material_id' => $line->material_id,
                    'fabric_color_id' => $line->fabric_color_id,
                    'warehouse_id' => $return->warehouse_id,
                    'inventory_lot_id' => $lot->id,
                    'quantity' => $line->returned_quantity,
                    'unit_id' => $line->base_unit_id,
                    'direction' => 'IN',
                    'unit_cost' => $line->unit_cost,
                    'total_cost' => $line->total_cost,
                    'reference_type' => MaterialReturn::class,
                    'reference_id' => $return->id,
                    'occurred_at' => $return->return_date->startOfDay(),
                    'performed_by_user_id' => $userObj->id,
                    'notes' => "سند مرتجع رقم {$return->return_number}",
                ]);
            }

            $return->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'received_by_user_id' => $return->received_by_user_id ?? $userObj->id,
            ]);

            return $return;
        });
    }

    /**
     * Post an inventory adjustment atomically.
     */
    public function postAdjustment(InventoryAdjustment $adjustment, User|int $user): InventoryAdjustment
    {
        $userObj = is_numeric($user) ? User::findOrFail($user) : $user;
        if (! $adjustment->isDraft()) {
            throw new Exception('لا يمكن اعتماد سند تسوية غير مسودة.');
        }

        if ($adjustment->lines()->count() === 0) {
            throw new Exception('لا يمكن اعتماد سند تسوية لا يحتوي على بنود خامات.');
        }

        return DB::transaction(function () use ($adjustment, $userObj) {
            $reasonCode = $adjustment->reason->code ?? 'ADJUSTMENT';

            foreach ($adjustment->lines as $line) {
                if ($line->isIn()) {
                    // ADJUSTMENT_IN
                    $lot = null;
                    if ($line->inventory_lot_id) {
                        $lot = InventoryLot::where('id', $line->inventory_lot_id)->lockForUpdate()->first();
                    }

                    if (! $lot) {
                        // Create a new lot for opening balance or unassigned positive adjustment
                        $lotCode = DocumentNumberService::generateLotCode();
                        $lot = InventoryLot::create([
                            'lot_code' => $lotCode,
                            'material_id' => $line->material_id,
                            'fabric_color_id' => $line->fabric_color_id,
                            'supplier_id' => null,
                            'warehouse_id' => $adjustment->warehouse_id,
                            'received_date' => $adjustment->adjustment_date,
                            'original_quantity' => $line->quantity,
                            'remaining_quantity' => $line->quantity,
                            'base_unit_id' => $line->base_unit_id,
                            'unit_cost' => $line->unit_cost,
                            'status' => 'ACTIVE',
                            'notes' => "تسوية إيجابية / رصيد افتتاح - سند رقم {$adjustment->adjustment_number}",
                        ]);
                    } else {
                        $lot->remaining_quantity += $line->quantity;
                        if ($lot->status === 'EXHAUSTED' && $lot->remaining_quantity > 0) {
                            $lot->status = 'ACTIVE';
                        }
                        $lot->save();
                    }

                    $movementType = ($reasonCode === 'OPENING_STOCK_MIGRATION') ? 'OPENING_BALANCE' : 'ADJUSTMENT_IN';

                    InventoryMovement::create([
                        'movement_number' => DocumentNumberService::generateMovementNumber(),
                        'movement_type' => $movementType,
                        'material_id' => $line->material_id,
                        'fabric_color_id' => $line->fabric_color_id,
                        'warehouse_id' => $adjustment->warehouse_id,
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $line->quantity,
                        'unit_id' => $line->base_unit_id,
                        'direction' => 'IN',
                        'unit_cost' => $line->unit_cost,
                        'total_cost' => round($line->quantity * $line->unit_cost, 4),
                        'reference_type' => InventoryAdjustment::class,
                        'reference_id' => $adjustment->id,
                        'occurred_at' => $adjustment->adjustment_date->startOfDay(),
                        'performed_by_user_id' => $userObj->id,
                        'notes' => "تسوية إضافة رقم {$adjustment->adjustment_number}",
                    ]);
                } else {
                    // ADJUSTMENT_OUT
                    if (! $line->inventory_lot_id) {
                        throw new Exception('يلزم تحديد اللوت الخصم منه لتسوية الخصم.');
                    }

                    $lot = InventoryLot::where('id', $line->inventory_lot_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($line->quantity > $lot->remaining_quantity) {
                        throw new Exception("كمية التسوية الخصم ({$line->quantity}) تتجاوز رصيد اللوت المتاح ({$lot->remaining_quantity}).");
                    }

                    $lot->remaining_quantity -= $line->quantity;
                    if ($lot->remaining_quantity <= 0) {
                        $lot->remaining_quantity = 0;
                        $lot->status = 'EXHAUSTED';
                    }
                    $lot->save();

                    InventoryMovement::create([
                        'movement_number' => DocumentNumberService::generateMovementNumber(),
                        'movement_type' => 'ADJUSTMENT_OUT',
                        'material_id' => $line->material_id,
                        'fabric_color_id' => $line->fabric_color_id,
                        'warehouse_id' => $adjustment->warehouse_id,
                        'inventory_lot_id' => $lot->id,
                        'quantity' => $line->quantity,
                        'unit_id' => $line->base_unit_id,
                        'direction' => 'OUT',
                        'unit_cost' => $lot->unit_cost,
                        'total_cost' => round($line->quantity * $lot->unit_cost, 4),
                        'reference_type' => InventoryAdjustment::class,
                        'reference_id' => $adjustment->id,
                        'occurred_at' => $adjustment->adjustment_date->startOfDay(),
                        'performed_by_user_id' => $userObj->id,
                        'notes' => "تسوية خصم رقم {$adjustment->adjustment_number}",
                    ]);
                }
            }

            $adjustment->update([
                'status' => 'POSTED',
                'posted_at' => now(),
                'adjusted_by_user_id' => $adjustment->adjusted_by_user_id ?? $userObj->id,
            ]);

            return $adjustment;
        });
    }

    /**
     * Create a draft material receipt with lines.
     */
    public function createReceipt(array $data, User|int $user): MaterialReceipt
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return DB::transaction(function () use ($data, $userId) {
            $receiptNumber = DocumentNumberService::generateReceiptNumber();

            $receipt = MaterialReceipt::create([
                'receipt_number' => $receiptNumber,
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'created_by_user_id' => $userId,
                'received_by_user_id' => $userId,
                'receipt_date' => $data['receipt_date'],
                'supplier_reference' => $data['supplier_reference'] ?? $data['supplier_invoice_number'] ?? null,
                'purchase_invoice_reference' => $data['purchase_invoice_reference'] ?? $data['supplier_invoice_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
            ]);

            $totalAmount = 0;

            $items = $data['items'] ?? $data['lines'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    $material = Material::findOrFail($item['material_id']);
                    $unitId = $item['unit_id'] ?? $item['purchase_unit_id'] ?? $material->purchase_unit_id ?? $material->base_unit_id;
                    $unit = UnitOfMeasure::findOrFail($unitId);
                    $conversionFactor = (float) ($item['conversion_factor'] ?? 1.0);

                    if (! isset($item['conversion_factor']) && $unitId != $material->base_unit_id) {
                        $conv = $material->unitConversions()
                            ->where('from_unit_id', $unitId)
                            ->where('to_unit_id', $material->base_unit_id)
                            ->first();
                        $conversionFactor = $conv ? (float) $conv->conversion_factor : 1.0;
                    }

                    $receivedQty = (float) ($item['quantity'] ?? $item['quantity_received'] ?? 0);
                    $baseQty = round($receivedQty * $conversionFactor, 4);
                    $unitCost = (float) ($item['unit_cost'] ?? $item['unit_cost_purchase'] ?? 0);
                    $totalLineCost = round($receivedQty * $unitCost, 4);
                    $unitCostBase = $baseQty > 0 ? round($totalLineCost / $baseQty, 4) : $unitCost;

                    $line = $receipt->lines()->create([
                        'purchase_order_line_id' => $item['purchase_order_line_id'] ?? null,
                        'material_id' => $material->id,
                        'fabric_color_id' => $item['fabric_color_id'] ?? null,
                        'purchase_unit_id' => $unit->id,
                        'base_unit_id' => $material->base_unit_id,
                        'quantity_received' => $receivedQty,
                        'conversion_factor' => $conversionFactor,
                        'base_quantity' => $baseQty,
                        'unit_cost_purchase' => $unitCost,
                        'unit_cost_base' => $unitCostBase,
                        'total_cost' => $totalLineCost,
                        'lot_reference' => $item['lot_number'] ?? $item['lot_reference'] ?? null,
                        'quality_note' => $item['quality_note'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);

                    $totalAmount += $totalLineCost;
                }
            }

            return $receipt;
        });
    }

    public function createMaterialReceipt(array $data, int $userId): MaterialReceipt
    {
        return $this->createReceipt($data, $userId);
    }

    /**
     * Create a draft material issue with lines.
     */
    public function createIssue(array $data, int $userId): MaterialIssue
    {
        return DB::transaction(function () use ($data, $userId) {
            $issueNumber = DocumentNumberService::generateIssueNumber();

            $issue = MaterialIssue::create([
                'issue_number' => $issueNumber,
                'warehouse_id' => $data['warehouse_id'],
                'department_id' => $data['department_id'],
                'created_by_user_id' => $userId,
                'issued_by_user_id' => $userId,
                'issue_date' => $data['issue_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
            ]);

            $totalCost = 0;

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $lot = InventoryLot::findOrFail($item['lot_id']);
                    $material = $lot->material;
                    $issuedQty = (float) $item['quantity'];

                    $lineCost = $issuedQty * $lot->unit_cost;

                    $issue->lines()->create([
                        'inventory_lot_id' => $lot->id,
                        'material_id' => $material->id,
                        'base_unit_id' => $material->base_unit_id,
                        'requested_quantity' => $issuedQty,
                        'issued_quantity' => $issuedQty,
                        'unit_cost' => $lot->unit_cost,
                        'total_cost' => $lineCost,
                        'notes' => $item['notes'] ?? null,
                    ]);

                    $totalCost += $lineCost;
                }
            }

            return $issue;
        });
    }

    public function createMaterialIssue(array $data, int $userId): MaterialIssue
    {
        return $this->createIssue($data, $userId);
    }

    /**
     * Create a draft material return with lines.
     */
    public function createReturn(array $data, int $userId): MaterialReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $returnNumber = DocumentNumberService::generateReturnNumber();

            $return = MaterialReturn::create([
                'return_number' => $returnNumber,
                'warehouse_id' => $data['warehouse_id'],
                'department_id' => $data['department_id'] ?? null,
                'created_by_user_id' => $userId,
                'received_by_user_id' => $userId,
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
            ]);

            $lines = $data['lines'] ?? $data['items'] ?? [];
            if (is_array($lines)) {
                foreach ($lines as $item) {
                    $issueLineId = $item['original_issue_line_id'] ?? ($item['issue_line_id'] ?? null);
                    $issueLine = $issueLineId ? MaterialIssueLine::find($issueLineId) : null;
                    $lotId = $item['inventory_lot_id'] ?? ($item['lot_id'] ?? ($issueLine?->inventory_lot_id ?? null));
                    $lot = InventoryLot::findOrFail($lotId);
                    $material = $lot->material;
                    $returnedQty = (float) ($item['returned_quantity'] ?? $item['quantity']);
                    $unitCost = $issueLine ? (float) $issueLine->unit_cost : (float) $lot->unit_cost;
                    $lineCost = round($returnedQty * $unitCost, 4);

                    $return->lines()->create([
                        'material_id' => $material->id,
                        'fabric_color_id' => $item['fabric_color_id'] ?? ($lot->fabric_color_id ?? null),
                        'inventory_lot_id' => $lot->id,
                        'original_issue_line_id' => $issueLine?->id,
                        'returned_quantity' => $returnedQty,
                        'base_unit_id' => $material->base_unit_id,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineCost,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return $return;
        });
    }

    public function createMaterialReturn(array $data, int $userId): MaterialReturn
    {
        return $this->createReturn($data, $userId);
    }

    /**
     * Create a draft inventory adjustment with lines.
     */
    public function createAdjustment(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $adjustmentNumber = DocumentNumberService::generateAdjustmentNumber();

            $adjustment = InventoryAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'warehouse_id' => $data['warehouse_id'],
                'reason_id' => $data['reason_id'],
                'created_by_user_id' => $userId,
                'adjusted_by_user_id' => $userId,
                'adjustment_date' => $data['adjustment_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
            ]);

            $lines = $data['lines'] ?? $data['items'] ?? [];
            if (is_array($lines)) {
                foreach ($lines as $item) {
                    $lotId = $item['inventory_lot_id'] ?? ($item['lot_id'] ?? null);
                    $lot = ! empty($lotId) ? InventoryLot::find($lotId) : null;
                    $materialId = $item['material_id'] ?? ($lot ? $lot->material_id : null);
                    $material = Material::findOrFail($materialId);
                    $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : ($lot ? (float) $lot->unit_cost : 0);
                    $qty = (float) $item['quantity'];

                    $rawType = $item['adjustment_type'] ?? 'ADJUSTMENT_OUT';
                    $adjType = match ($rawType) {
                        'INCREASE', 'ADJUSTMENT_IN' => 'ADJUSTMENT_IN',
                        'DECREASE', 'ADJUSTMENT_OUT' => 'ADJUSTMENT_OUT',
                        default => 'ADJUSTMENT_OUT',
                    };

                    $adjustment->lines()->create([
                        'adjustment_type' => $adjType,
                        'inventory_lot_id' => $lot?->id,
                        'material_id' => $material->id,
                        'base_unit_id' => $material->base_unit_id,
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'total_cost' => round($qty * $unitCost, 4),
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return $adjustment;
        });
    }

    public function createInventoryAdjustment(array $data, int $userId): InventoryAdjustment
    {
        return $this->createAdjustment($data, $userId);
    }

    public function calculateMaterialStockBalance(int $materialId): float
    {
        $inSum = InventoryMovement::where('material_id', $materialId)
            ->where('direction', 'IN')
            ->sum('quantity');

        $outSum = InventoryMovement::where('material_id', $materialId)
            ->where('direction', 'OUT')
            ->sum('quantity');

        return (float) ($inSum - $outSum);
    }

    public function calculateMaterialValuation(int $materialId): float
    {
        return (float) InventoryLot::where('material_id', $materialId)
            ->where('remaining_quantity', '>', 0)
            ->get()
            ->sum(fn ($lot) => $lot->remaining_quantity * $lot->unit_cost);
    }

    /**
     * Reconcile cached lot remaining quantity against full movement history.
     */
    public function reconcileLotBalance(InventoryLot $lot): array
    {
        $inSum = InventoryMovement::where('inventory_lot_id', $lot->id)
            ->where('direction', 'IN')
            ->sum('quantity');

        $outSum = InventoryMovement::where('inventory_lot_id', $lot->id)
            ->where('direction', 'OUT')
            ->sum('quantity');

        $calculatedRemaining = (float) ($inSum - $outSum);
        $cachedRemaining = (float) $lot->remaining_quantity;

        return [
            'lot_code' => $lot->lot_code,
            'cached_remaining' => $cachedRemaining,
            'in_sum' => (float) $inSum,
            'out_sum' => (float) $outSum,
            'calculated_remaining' => $calculatedRemaining,
            'is_reconciled' => abs($cachedRemaining - $calculatedRemaining) < 0.0001,
        ];
    }
}
