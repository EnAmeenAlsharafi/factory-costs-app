<?php

namespace App\Services;

use App\Models\FabricOrderAllocation;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the fabric each bed needs into one draft purchase order per supplier, with the meters of the
 * same catalog and colour added together, and remembers which beds every line is for.
 */
class FabricOrderService
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    /**
     * Open production orders whose fabric has not been ordered yet.
     *
     * @return Collection<int, ProductionOrder>
     */
    public function candidates(): Collection
    {
        return ProductionOrder::query()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereHas('customerOrder', fn (Builder $q) => $q->whereIn('status', ['APPROVED_FOR_PRODUCTION', 'PENDING_PRODUCTION_REVIEW']))
            ->where(fn (Builder $q) => $q->whereNotNull('fabric_material_id')
                ->orWhereHas('customerOrderLine', fn (Builder $line) => $line->where('special_fabric', true)))
            ->whereDoesntHave('fabricOrderAllocations', fn (Builder $q) => $q
                ->whereHas('purchaseOrderLine.purchaseOrder', fn (Builder $po) => $po->where('status', '!=', 'CANCELLED')))
            ->with([
                'customerOrder.customer',
                'customerOrderLine',
                'productModel',
                'fabricMaterial.fabricSpec.supplier',
                'fabricMaterial.baseUnit',
                'fabricColor',
                'materialRequirements',
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Meters of the order's own fabric planned by its recipe, or null when no recipe gives it.
     */
    public function plannedMeters(ProductionOrder $order): ?float
    {
        if (! $order->fabric_material_id) {
            return null;
        }

        $meters = (float) $order->materialRequirements
            ->where('material_id', $order->fabric_material_id)
            ->sum('total_planned_quantity');

        return $meters > 0 ? round($meters, 2) : null;
    }

    /**
     * Whether the order's fabric is identified well enough to be ordered: material, catalog supplier and colour.
     */
    public function isOrderable(ProductionOrder $order): bool
    {
        return $order->fabric_material_id
            && $order->fabricColor
            && $order->fabricMaterial?->fabricSpec?->supplier;
    }

    /**
     * @param  array<int, float>  $metersByOrder  production order id => meters for that order
     * @return Collection<int, PurchaseOrder> one draft purchase order per supplier
     */
    public function createOrders(array $metersByOrder, Warehouse $warehouse, User $user, float $extraPercent = 0, ?string $notes = null): Collection
    {
        $orders = $this->candidates()->whereIn('id', array_keys($metersByOrder));
        $missing = array_diff(array_keys($metersByOrder), $orders->pluck('id')->all());
        if ($missing !== []) {
            throw ValidationException::withMessages(['items' => 'بعض الطلبات المحددة طُلب قماشها مسبقاً أو لم تعد مفتوحة. حدّث الصفحة.']);
        }

        foreach ($orders as $order) {
            if (! $this->isOrderable($order)) {
                throw ValidationException::withMessages(['items' => "قماش الأمر {$order->production_order_number} غير مربوط بمورد وكتالوج ولون."]);
            }
            if ((float) $metersByOrder[$order->id] <= 0) {
                throw ValidationException::withMessages(['items' => "أدخل كمية القماش بالمتر للأمر {$order->production_order_number}."]);
            }
        }

        return DB::transaction(function () use ($orders, $metersByOrder, $warehouse, $user, $extraPercent, $notes) {
            return $orders
                ->groupBy(fn (ProductionOrder $order) => $order->fabricMaterial->fabricSpec->supplier_id)
                ->map(function (Collection $supplierOrders, int $supplierId) use ($metersByOrder, $warehouse, $user, $extraPercent, $notes) {
                    $groups = $supplierOrders->groupBy(fn (ProductionOrder $order) => $order->fabric_material_id.'-'.$order->fabric_color_id)->values();

                    $purchaseOrder = $this->purchaseOrderService->createOrder([
                        'supplier_id' => $supplierId,
                        'warehouse_id' => $warehouse->id,
                        'order_date' => now(),
                        'notes' => $notes,
                        'lines' => $groups->map(function (Collection $group) use ($metersByOrder, $extraPercent): array {
                            $first = $group->first();
                            $meters = $group->sum(fn (ProductionOrder $order) => (float) $metersByOrder[$order->id]);

                            return [
                                'material_id' => $first->fabric_material_id,
                                'fabric_color_id' => $first->fabric_color_id,
                                'fabric_color_code' => $first->fabric_color_code ?? $first->fabricColor?->color_code,
                                'fabric_supplier_color_code' => $first->fabric_supplier_color_code ?? $first->fabricColor?->supplier_color_code,
                                'ordered_quantity' => $this->roundUpToHalf($meters * (1 + $extraPercent / 100)),
                                'purchase_unit_id' => $first->fabricMaterial->base_unit_id,
                                'conversion_factor' => 1,
                                'unit_price' => $this->lastUnitPrice($first->fabric_material_id),
                                'notes' => $group->count().' سرير',
                            ];
                        })->all(),
                    ], $user);

                    // Lines are created in the same order as the groups.
                    $purchaseOrder->lines->values()->each(function (PurchaseOrderLine $line, int $index) use ($groups, $metersByOrder) {
                        foreach ($groups[$index] as $order) {
                            FabricOrderAllocation::create([
                                'purchase_order_line_id' => $line->id,
                                'production_order_id' => $order->id,
                                'meters' => round((float) $metersByOrder[$order->id], 2),
                            ]);
                        }
                    });

                    return $purchaseOrder;
                })
                ->values();
        });
    }

    private function roundUpToHalf(float $meters): float
    {
        return ceil(round($meters, 4) * 2) / 2;
    }

    private function lastUnitPrice(int $materialId): float
    {
        return (float) (PurchaseOrderLine::where('material_id', $materialId)
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('status', '!=', 'CANCELLED'))
            ->latest('id')
            ->value('unit_price') ?? 0);
    }
}
