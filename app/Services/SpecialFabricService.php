<?php

namespace App\Services;

use App\Models\CustomerOrderLine;
use App\Models\FabricColor;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\MaterialReceiptLine;
use App\Models\ProductionMaterialRequestLine;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpecialFabricService
{
    public function isSpecial(ProductionOrder $order): bool
    {
        return (bool) $order->customerOrderLine?->special_fabric;
    }

    public function assertRegistered(ProductionOrder|CustomerOrderLine $record): void
    {
        $fabric = Material::with('fabricSpec.supplier', 'category')->find($record->fabric_material_id);
        $color = FabricColor::find($record->fabric_color_id);
        if (! $fabric?->is_active || $fabric->category?->code !== 'FABRIC'
            || ! $fabric->base_unit_id || ! $fabric->fabricSpec?->supplier?->is_active
            || (! $fabric->fabricSpec->catalog_number && ! $fabric->fabricSpec->catalog_name)
            || ! $color?->is_active || $color->material_id !== $fabric->id
            || $record->fabric_supplier_id !== $fabric->fabricSpec->supplier_id) {
            throw ValidationException::withMessages(['fabric' => 'يجب ربط القماش الخاص بصنف قماش مسجل بمورده وكتالوجه ووحدته ولونه قبل الصرف والتنجيد.']);
        }
    }

    public function link(CustomerOrderLine $line, int $colorId, User $user): void
    {
        DB::transaction(function () use ($line, $colorId, $user) {
            $line = CustomerOrderLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $orders = $line->productionOrders()->lockForUpdate()->get();
            if (! $line->special_fabric || $line->customerOrder->status === 'CANCELLED') {
                throw ValidationException::withMessages(['fabric' => 'الربط متاح لبنود القماش الخاص في الطلبات غير الملغاة فقط.']);
            }
            $color = FabricColor::with('material.fabricSpec')->findOrFail($colorId);
            $attributes = [
                'fabric_material_id' => $color->material_id,
                'fabric_supplier_id' => $color->material->fabricSpec?->supplier_id,
                'fabric_color_id' => $color->id,
                'fabric_color_code' => $color->color_code,
                'fabric_supplier_color_code' => $color->supplier_color_code,
            ];
            $old = $line->only(array_keys($attributes));
            $line->fill($attributes);
            $this->assertRegistered($line);
            foreach ($orders as $order) {
                if (ProductionMaterialRequestLine::whereHas('request', fn ($query) => $query->where('production_order_id', $order->id))
                    ->whereHas('material.category', fn ($query) => $query->where('code', 'FABRIC'))->exists()
                    || MaterialIssueLine::whereHas('issue', fn ($query) => $query->where('production_order_id', $order->id)->where('status', 'POSTED'))
                        ->whereHas('material.category', fn ($query) => $query->where('code', 'FABRIC'))->exists()
                    || $order->operations()->whereIn('operation_code', ['OP_UPHOLSTERY', 'OP_BOX_PREP'])->whereNotNull('started_at')->exists()) {
                    throw ValidationException::withMessages(['fabric' => 'لا يمكن تغيير الربط بعد طلب القماش أو صرفه أو بدء تلبيسه. عالج طلب الصرف أولاً مع مسؤول المخزون.']);
                }
            }
            $line->save();
            foreach ($orders as $order) {
                $order->update($attributes);
                app(ProductionMaterialRequestService::class)->createMaterialRequirementsFromRecipe($order->fresh());
            }
            app(CustomerOrderService::class)->recordOrderChange($line->customerOrder, $line->id, 'special_fabric_link', $old, $attributes, $user,
                $line->customerOrder->status === 'APPROVED_FOR_PRODUCTION', 'ربط مخزني للقماش الخاص؛ وصف العميل والسعر المعتمد محفوظان.');
        });
    }

    public function assertIssue(MaterialIssue $issue, MaterialIssueLine $line, InventoryLot $lot): void
    {
        if (! $issue->production_order_id) {
            return;
        }
        $order = ProductionOrder::whereKey($issue->production_order_id)->lockForUpdate()->firstOrFail();
        if (! $this->isSpecial($order)
            || ($line->material?->category?->code !== 'FABRIC' && $lot->material?->category?->code !== 'FABRIC')) {
            return;
        }
        $this->assertRegistered($order);
        $received = MaterialReceiptLine::whereKey($lot->receipt_line_id)
            ->where('material_id', $order->fabric_material_id)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))->exists();
        if (! $received || $lot->material_id !== $order->fabric_material_id || $line->material_id !== $order->fabric_material_id
            || $lot->fabric_color_id !== $order->fabric_color_id || ($line->fabric_color_id && $line->fabric_color_id !== $order->fabric_color_id)
            || $lot->supplier_id !== $order->fabric_supplier_id || $lot->warehouse_id !== $issue->warehouse_id
            || $lot->base_unit_id !== $line->base_unit_id || $line->issued_quantity <= 0 || $lot->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['fabric' => 'يجب صرف القماش واللون والمورد المعتمدين من دفعة استلام مخزنية معتمدة ومتاحة.']);
        }
    }

    public function assertOperationReady(ProductionOrderOperation $operation): void
    {
        if (! in_array($operation->operation_code, ['OP_UPHOLSTERY', 'OP_BOX_PREP'], true)
            && $operation->workCenter?->department?->code !== 'UPHOLSTERY') {
            return;
        }
        $order = ProductionOrder::whereKey($operation->production_order_id)->lockForUpdate()->firstOrFail();
        if (! $this->isSpecial($order)) {
            return;
        }
        $this->assertRegistered($order);
        $issued = MaterialIssueLine::where('material_id', $order->fabric_material_id)
            ->whereHas('lot', fn ($query) => $query->where('material_id', $order->fabric_material_id)
                ->where('fabric_color_id', $order->fabric_color_id)->where('supplier_id', $order->fabric_supplier_id)
                ->whereHas('receiptLine.receipt', fn ($receipt) => $receipt->where('status', 'POSTED')))
            ->whereHas('issue', fn ($query) => $query->where('production_order_id', $order->id)->where('status', 'POSTED'))
            ->where('issued_quantity', '>', 0)->exists();
        if (! $issued) {
            throw ValidationException::withMessages(['fabric' => 'لا يمكن بدء التنجيد أو تسجيل إنجازه قبل استلام القماش الخاص وصرفه على أمر الإنتاج.']);
        }
    }
}
