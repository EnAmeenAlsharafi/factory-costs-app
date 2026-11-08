<?php

namespace App\Services;

use App\Models\CustomerOrderLine;
use App\Models\DeliveryOrderLine;
use App\Models\InventoryLot;
use App\Models\MaterialReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReadyProductOrderAllocation;
use App\Models\ReadyProductSupplySetting;
use App\Models\Supplier;
use App\Models\SupplierProductionRequest;
use App\Models\SupplierStockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supplier-made ready products (mattresses): what we hold, what the supplier holds for us,
 * what open customer orders still need, supply orders to the supplier and production requests.
 */
class ReadyProductSupplyService
{
    /**
     * Delivery statuses at which ready stock has already left our store.
     */
    private const DISPATCHED_STATUSES = ['OUT_FOR_DELIVERY', 'DELIVERED', 'INSTALLATION_COMPLETED'];

    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    /**
     * One row per tracked size with its balances and whether it is at or below the reorder level.
     *
     * @return Collection<int, array{setting: ReadyProductSupplySetting, on_hand: float, at_supplier: float, reserved: float, on_order: float, available: float, is_low: bool, suggested: int}>
     */
    public function overview(): Collection
    {
        $settings = ReadyProductSupplySetting::with(['material.readyParent', 'material.baseUnit', 'supplier'])->get();
        $ids = $settings->pluck('material_id')->all();
        if ($ids === []) {
            return collect();
        }

        $onHand = $this->onHand($ids);
        $atSupplier = SupplierStockMovement::whereIn('material_id', $ids)->groupBy('material_id')->selectRaw('material_id, SUM(quantity) as total')->pluck('total', 'material_id');
        $reserved = $this->reserved($ids);
        $onOrder = PurchaseOrderLine::whereIn('material_id', $ids)
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->whereNotIn('status', ['CANCELLED', 'CLOSED']))
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(ordered_base_quantity - received_base_quantity) as total')
            ->pluck('total', 'material_id');

        return $settings->map(function (ReadyProductSupplySetting $setting) use ($onHand, $atSupplier, $reserved, $onOrder) {
            $id = $setting->material_id;
            $available = (float) ($onHand[$id] ?? 0) + (float) ($atSupplier[$id] ?? 0) - (float) ($reserved[$id] ?? 0);
            $isLow = $setting->reorder_level > 0 ? $available <= $setting->reorder_level : $available < 0;

            return [
                'setting' => $setting,
                'on_hand' => (float) ($onHand[$id] ?? 0),
                'at_supplier' => (float) ($atSupplier[$id] ?? 0),
                'reserved' => (float) ($reserved[$id] ?? 0),
                'on_order' => max(0.0, (float) ($onOrder[$id] ?? 0)),
                'available' => $available,
                'is_low' => $isLow,
                'suggested' => $isLow ? $this->suggestedBatch($setting, $available) : 0,
            ];
        })->sortBy(fn (array $row) => [$row['is_low'] ? 0 : 1, $this->productName($row['setting']), $row['setting']->material->ready_option_label])->values();
    }

    /**
     * How many tracked sizes are at or below their reorder level (for the sidebar badge and dashboard).
     */
    public function lowStockCount(): int
    {
        return once(fn () => $this->overview()->where('is_low', true)->count());
    }

    /**
     * Supplier production comes in batches: order enough to rise back above the reorder level, in whole batches.
     */
    public function suggestedBatch(ReadyProductSupplySetting $setting, float $available): int
    {
        $batch = max(1, $setting->production_batch_size);
        $shortfall = max(1, (int) ceil($setting->reorder_level - $available + 1));

        return (int) (ceil($shortfall / $batch) * $batch);
    }

    public function productName(ReadyProductSupplySetting $setting): string
    {
        $parent = $setting->material->readyParent;
        $name = $parent?->name_ar ?? $setting->material->name_ar;

        return trim(preg_replace('/\s*\|\s*مقاسات متعددة\s*$/u', '', $name));
    }

    /**
     * Open customer order lines for tracked products that still need supplying.
     *
     * @return Collection<int, CustomerOrderLine> each with remaining_quantity set
     */
    public function demand(): Collection
    {
        $settings = ReadyProductSupplySetting::whereNotNull('supplier_id')->pluck('material_id');
        if ($settings->isEmpty()) {
            return collect();
        }

        $lines = CustomerOrderLine::query()
            ->where('line_type', 'READY_STOCK')
            ->whereIn('material_id', $settings)
            ->whereHas('customerOrder', fn (Builder $q) => $q->whereNotIn('status', ['CANCELLED', 'DRAFT']))
            ->with(['customerOrder.customer', 'material.readyParent', 'material.supplySetting.supplier', 'parentLine'])
            ->get();

        $dispatched = $this->dispatchedByLine($lines->pluck('id')->all());
        $allocated = ReadyProductOrderAllocation::whereIn('customer_order_line_id', $lines->pluck('id'))
            ->whereHas('purchaseOrderLine.purchaseOrder', fn (Builder $q) => $q->where('status', '!=', 'CANCELLED'))
            ->groupBy('customer_order_line_id')
            ->selectRaw('customer_order_line_id, SUM(quantity) as total')
            ->pluck('total', 'customer_order_line_id');

        return $lines
            ->each(fn (CustomerOrderLine $line) => $line->setAttribute(
                'remaining_quantity',
                max(0, (float) $line->quantity - (float) ($dispatched[$line->id] ?? 0) - (float) ($allocated[$line->id] ?? 0))
            ))
            ->filter(fn (CustomerOrderLine $line) => $line->remaining_quantity > 0)
            ->sortBy(fn (CustomerOrderLine $line) => [$line->customerOrder->requested_delivery_date?->timestamp ?? PHP_INT_MAX, $line->id])
            ->values();
    }

    /**
     * One draft purchase order per supplier, quantities of the same size added together.
     *
     * @param  array<int, float>  $quantityByLine  customer order line id => quantity to supply
     * @return Collection<int, PurchaseOrder>
     */
    public function createSupplyOrders(array $quantityByLine, User $user, ?string $notes = null): Collection
    {
        $demand = $this->demand()->keyBy('id');

        foreach ($quantityByLine as $lineId => $quantity) {
            $line = $demand->get($lineId);
            if (! $line) {
                throw ValidationException::withMessages(['items' => 'بعض البنود المحددة طُلبت مسبقاً أو لم تعد مفتوحة. حدّث الصفحة.']);
            }
            if ($quantity <= 0 || $quantity > $line->remaining_quantity + 0.0001) {
                throw ValidationException::withMessages(['items' => "الكمية المطلوبة لطلب {$line->customerOrder->order_number} يجب أن تكون بين 1 و{$line->remaining_quantity}."]);
            }
        }

        $warehouse = Warehouse::where('code', 'READY_GOODS')->first() ?? Warehouse::active()->orderBy('id')->firstOrFail();
        $picked = $demand->only(array_keys($quantityByLine));

        return DB::transaction(fn () => $picked
            ->groupBy(fn (CustomerOrderLine $line) => $line->material->supplySetting->supplier_id)
            ->map(function (Collection $supplierLines, int $supplierId) use ($quantityByLine, $warehouse, $user, $notes) {
                $groups = $supplierLines->groupBy('material_id')->values();

                $purchaseOrder = $this->purchaseOrderService->createOrder([
                    'supplier_id' => $supplierId,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now(),
                    'notes' => $notes,
                    'lines' => $groups->map(fn (Collection $group) => [
                        'material_id' => $group->first()->material_id,
                        'ordered_quantity' => $group->sum(fn (CustomerOrderLine $line) => (float) $quantityByLine[$line->id]),
                        'purchase_unit_id' => $group->first()->material->base_unit_id,
                        'conversion_factor' => 1,
                        'unit_price' => $this->lastUnitPrice($group->first()->material_id),
                    ])->all(),
                ], $user);

                $purchaseOrder->lines->values()->each(function (PurchaseOrderLine $poLine, int $index) use ($groups, $quantityByLine) {
                    foreach ($groups[$index] as $line) {
                        ReadyProductOrderAllocation::create([
                            'purchase_order_line_id' => $poLine->id,
                            'customer_order_line_id' => $line->id,
                            'quantity' => (float) $quantityByLine[$line->id],
                        ]);
                    }
                });

                return $purchaseOrder;
            })
            ->values());
    }

    /**
     * @param  array<int, int>  $quantityByMaterial
     */
    public function createProductionRequest(Supplier $supplier, array $quantityByMaterial, User $user, ?string $notes = null): SupplierProductionRequest
    {
        $settings = ReadyProductSupplySetting::whereIn('material_id', array_keys($quantityByMaterial))->get()->keyBy('material_id');

        foreach ($quantityByMaterial as $materialId => $quantity) {
            $setting = $settings->get($materialId);
            if (! $setting || (int) $setting->supplier_id !== $supplier->id) {
                throw ValidationException::withMessages(['items' => 'كل المقاسات في طلب الإنتاج يجب أن تكون من نفس المورد.']);
            }
            if ($quantity < $setting->production_batch_size) {
                throw ValidationException::withMessages(['items' => "أقل كمية إنتاج لمقاس {$setting->material->ready_option_label} هي {$setting->production_batch_size}."]);
            }
        }

        return DB::transaction(function () use ($supplier, $quantityByMaterial, $user, $notes) {
            $request = SupplierProductionRequest::create([
                'request_number' => $this->nextRequestNumber(),
                'supplier_id' => $supplier->id,
                'status' => 'REQUESTED',
                'request_date' => now()->toDateString(),
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            foreach ($quantityByMaterial as $materialId => $quantity) {
                $request->lines()->create(['material_id' => $materialId, 'quantity' => (int) $quantity]);
            }

            return $request->load('lines.material', 'supplier');
        });
    }

    /**
     * The supplier confirmed the batch: it is now held at the supplier for us.
     *
     * @param  array<int, int>  $producedByLine  production request line id => quantity actually produced
     */
    public function confirmProduction(SupplierProductionRequest $request, array $producedByLine, User $user): SupplierProductionRequest
    {
        if ($request->status !== 'REQUESTED') {
            throw ValidationException::withMessages(['request' => 'هذا الطلب ليس بانتظار الإنتاج.']);
        }

        return DB::transaction(function () use ($request, $producedByLine, $user) {
            foreach ($request->lines as $line) {
                $produced = max(0, (int) ($producedByLine[$line->id] ?? $line->quantity));
                $line->update(['produced_quantity' => $produced]);

                if ($produced > 0) {
                    SupplierStockMovement::create([
                        'material_id' => $line->material_id,
                        'supplier_id' => $request->supplier_id,
                        'movement_type' => 'PRODUCED',
                        'quantity' => $produced,
                        'supplier_production_request_id' => $request->id,
                        'notes' => 'طلب إنتاج '.$request->request_number,
                        'created_by_user_id' => $user->id,
                    ]);
                }
            }

            $request->update(['status' => 'PRODUCED', 'produced_at' => now(), 'produced_by_user_id' => $user->id]);

            return $request->fresh('lines.material');
        });
    }

    /**
     * Set the supplier's held quantity of a size to what they report (opening balance or count correction).
     */
    public function setSupplierBalance(ReadyProductSupplySetting $setting, float $quantity, User $user, ?string $notes = null): void
    {
        if (! $setting->supplier_id) {
            throw ValidationException::withMessages(['supplier_id' => 'حدد مورد هذا المقاس أولاً.']);
        }

        $current = (float) SupplierStockMovement::where('material_id', $setting->material_id)->sum('quantity');
        $difference = round($quantity - $current, 2);
        if ($difference == 0) {
            return;
        }

        SupplierStockMovement::create([
            'material_id' => $setting->material_id,
            'supplier_id' => $setting->supplier_id,
            'movement_type' => SupplierStockMovement::where('material_id', $setting->material_id)->exists() ? 'ADJUSTMENT' : 'OPENING',
            'quantity' => $difference,
            'notes' => $notes,
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * Goods received from the supplier came out of the stock they held for us.
     */
    public function recordDeliveryFromReceipt(MaterialReceipt $receipt, PurchaseOrder $purchaseOrder, User $user): void
    {
        $tracked = ReadyProductSupplySetting::whereIn('material_id', $receipt->lines->pluck('material_id'))->pluck('material_id')->flip();

        foreach ($receipt->lines as $line) {
            if (! $tracked->has($line->material_id)) {
                continue;
            }

            $held = (float) SupplierStockMovement::where('material_id', $line->material_id)->sum('quantity');
            $taken = min((float) $line->base_quantity, max(0.0, $held));
            if ($taken <= 0) {
                continue;
            }

            SupplierStockMovement::create([
                'material_id' => $line->material_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'movement_type' => 'DELIVERED',
                'quantity' => -$taken,
                'purchase_order_id' => $purchaseOrder->id,
                'material_receipt_id' => $receipt->id,
                'notes' => 'استلام '.$receipt->receipt_number,
                'created_by_user_id' => $user->id,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $materialIds
     * @return Collection<int, float>
     */
    private function onHand(array $materialIds): Collection
    {
        return InventoryLot::whereIn('material_id', $materialIds)
            ->where('status', 'ACTIVE')
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(remaining_quantity) as total')
            ->pluck('total', 'material_id');
    }

    /**
     * Quantity on open customer orders not yet dispatched to the customer.
     *
     * @param  array<int, int>  $materialIds
     * @return Collection<int, float>
     */
    private function reserved(array $materialIds): Collection
    {
        $lines = CustomerOrderLine::where('line_type', 'READY_STOCK')
            ->whereIn('material_id', $materialIds)
            ->whereHas('customerOrder', fn (Builder $q) => $q->whereNotIn('status', ['CANCELLED', 'DRAFT']))
            ->get(['id', 'material_id', 'quantity']);
        $dispatched = $this->dispatchedByLine($lines->pluck('id')->all());

        return $lines->groupBy('material_id')->map(fn (Collection $group) => $group->sum(
            fn (CustomerOrderLine $line) => max(0, (float) $line->quantity - (float) ($dispatched[$line->id] ?? 0))
        ));
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return Collection<int, float>
     */
    private function dispatchedByLine(array $lineIds): Collection
    {
        if ($lineIds === []) {
            return collect();
        }

        return DeliveryOrderLine::whereIn('customer_order_line_id', $lineIds)
            ->whereHas('deliveryOrder', fn (Builder $q) => $q->whereIn('status', self::DISPATCHED_STATUSES))
            ->groupBy('customer_order_line_id')
            ->selectRaw('customer_order_line_id, SUM(quantity) as total')
            ->pluck('total', 'customer_order_line_id');
    }

    private function lastUnitPrice(int $materialId): float
    {
        return (float) (PurchaseOrderLine::where('material_id', $materialId)
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('status', '!=', 'CANCELLED'))
            ->latest('id')
            ->value('unit_price') ?? 0);
    }

    private function nextRequestNumber(): string
    {
        $prefix = 'SPR-'.now()->year.'-';
        $last = SupplierProductionRequest::where('request_number', 'like', $prefix.'%')->lockForUpdate()->max('request_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
