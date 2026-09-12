<?php

namespace App\Http\Requests;

use App\Models\InventoryLot;
use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rawLines = $this->input('lines', $this->input('items', []));

        if (is_array($rawLines)) {
            $normalized = array_map(function ($item) {
                $lotId = $item['inventory_lot_id'] ?? ($item['lot_id'] ?? null);
                $lot = $lotId ? InventoryLot::find($lotId) : null;
                $rawType = $item['adjustment_type'] ?? 'ADJUSTMENT_OUT';

                $adjType = match ($rawType) {
                    'INCREASE', 'ADJUSTMENT_IN' => 'ADJUSTMENT_IN',
                    'DECREASE', 'ADJUSTMENT_OUT' => 'ADJUSTMENT_OUT',
                    default => 'ADJUSTMENT_OUT',
                };

                $materialId = $item['material_id'] ?? ($lot?->material_id ?? null);
                $material = $materialId ? Material::find($materialId) : null;

                return [
                    'inventory_lot_id' => $lotId,
                    'material_id' => $materialId,
                    'fabric_color_id' => $item['fabric_color_id'] ?? ($lot?->fabric_color_id ?? null),
                    'base_unit_id' => $item['base_unit_id'] ?? ($lot?->base_unit_id ?? ($material?->base_unit_id ?? null)),
                    'adjustment_type' => $adjType,
                    'quantity' => $item['quantity'] ?? null,
                    'unit_cost' => $item['unit_cost'] ?? ($lot?->unit_cost ?? 0),
                    'notes' => $item['notes'] ?? null,
                ];
            }, $rawLines);

            $this->merge(['lines' => $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'reason_id' => ['required', 'exists:inventory_adjustment_reasons,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.inventory_lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'lines.*.adjustment_type' => ['required', 'in:ADJUSTMENT_IN,ADJUSTMENT_OUT'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_id' => 'المستودع',
            'adjustment_date' => 'تاريخ التسوية',
            'reason_id' => 'سبب التسوية',
            'lines' => 'بنود التسوية',
            'lines.*.material_id' => 'المادة الخام',
            'lines.*.adjustment_type' => 'نوع التسوية',
            'lines.*.quantity' => 'الكمية',
            'lines.*.unit_cost' => 'تكلفة الوحدة',
        ];
    }
}
