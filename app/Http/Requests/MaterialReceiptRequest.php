<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;

class MaterialReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.receive') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rawLines = $this->input('lines', $this->input('items', []));

        if (is_array($rawLines)) {
            $normalized = array_map(function ($item) {
                $unitId = $item['purchase_unit_id'] ?? ($item['unit_id'] ?? null);
                $materialId = $item['material_id'] ?? null;
                $conversionFactor = $item['conversion_factor'] ?? null;

                if (! $conversionFactor && $materialId && $unitId) {
                    $mat = Material::find($materialId);
                    if ($mat && $mat->base_unit_id != $unitId) {
                        $conv = $mat->unitConversions()
                            ->where('from_unit_id', $unitId)
                            ->where('to_unit_id', $mat->base_unit_id)
                            ->first();
                        $conversionFactor = $conv ? (float) $conv->conversion_factor : 1.0;
                    }
                }
                $conversionFactor = (float) ($conversionFactor ?? 1.0);

                return [
                    'material_id' => $materialId,
                    'fabric_color_id' => $item['fabric_color_id'] ?? null,
                    'quantity_received' => $item['quantity_received'] ?? ($item['quantity'] ?? null),
                    'purchase_unit_id' => $unitId,
                    'conversion_factor' => $conversionFactor,
                    'unit_cost_purchase' => $item['unit_cost_purchase'] ?? ($item['unit_cost'] ?? 0),
                    'supplier_material_code' => $item['supplier_material_code'] ?? null,
                    'quality_note' => $item['quality_note'] ?? null,
                    'lot_reference' => $item['lot_reference'] ?? ($item['lot_number'] ?? null),
                    'notes' => $item['notes'] ?? null,
                ];
            }, $rawLines);

            $this->merge(['lines' => $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'purchase_invoice_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.quantity_received' => ['required', 'numeric', 'gt:0'],
            'lines.*.purchase_unit_id' => ['required', 'exists:units_of_measure,id'],
            'lines.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost_purchase' => ['required', 'numeric', 'gte:0'],
            'lines.*.supplier_material_code' => ['nullable', 'string', 'max:100'],
            'lines.*.quality_note' => ['nullable', 'string', 'max:255'],
            'lines.*.lot_reference' => ['nullable', 'string', 'max:100'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'المورد',
            'warehouse_id' => 'المستودع',
            'receipt_date' => 'تاريخ الاستلام',
            'lines' => 'بنود الإيصال',
            'lines.*.material_id' => 'المادة الخام',
            'lines.*.quantity_received' => 'الكمية المستلمة',
            'lines.*.purchase_unit_id' => 'وحدة الشراء',
            'lines.*.unit_cost_purchase' => 'سعر شراء الوحدة',
        ];
    }
}
