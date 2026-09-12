<?php

namespace App\Http\Requests;

use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialIssueLine;
use Illuminate\Foundation\Http\FormRequest;

class MaterialReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.return') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rawLines = $this->input('lines', $this->input('items', []));

        if (empty($rawLines) && $this->filled('inventory_lot_id')) {
            $rawLines = [[
                'material_id' => $this->input('material_id'),
                'fabric_color_id' => $this->input('fabric_color_id'),
                'inventory_lot_id' => $this->input('inventory_lot_id'),
                'original_issue_line_id' => $this->input('original_issue_line_id'),
                'returned_quantity' => $this->input('returned_quantity'),
                'notes' => $this->input('notes'),
            ]];
        }

        if (is_array($rawLines) && count($rawLines) > 0) {
            $normalized = array_map(function ($item) {
                $issueLineId = $item['original_issue_line_id'] ?? ($item['issue_line_id'] ?? null);
                $issueLine = $issueLineId ? MaterialIssueLine::find($issueLineId) : null;
                $lotId = $item['inventory_lot_id'] ?? ($item['lot_id'] ?? ($issueLine?->inventory_lot_id ?? null));
                $lot = $lotId ? InventoryLot::find($lotId) : null;

                $materialId = $item['material_id'] ?? ($lot?->material_id ?? ($issueLine?->material_id ?? null));
                $material = $materialId ? Material::find($materialId) : null;

                return [
                    'original_issue_line_id' => $issueLineId,
                    'inventory_lot_id' => $lotId,
                    'material_id' => $materialId,
                    'fabric_color_id' => $item['fabric_color_id'] ?? ($lot?->fabric_color_id ?? ($issueLine?->fabric_color_id ?? null)),
                    'base_unit_id' => $item['base_unit_id'] ?? ($lot?->base_unit_id ?? ($material?->base_unit_id ?? null)),
                    'returned_quantity' => $item['returned_quantity'] ?? ($item['quantity'] ?? null),
                    'unit_cost' => $item['unit_cost'] ?? ($issueLine?->unit_cost ?? ($lot?->unit_cost ?? 0)),
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
            'return_date' => ['required', 'date', 'before_or_equal:today'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.inventory_lot_id' => ['required', 'exists:inventory_lots,id'],
            'lines.*.original_issue_line_id' => ['nullable', 'exists:material_issue_lines,id'],
            'lines.*.returned_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_id' => 'المستودع',
            'return_date' => 'تاريخ الإرجاع',
            'department_id' => 'القسم',
            'lines' => 'بنود المرتجع',
            'lines.*.material_id' => 'المادة الخام',
            'lines.*.inventory_lot_id' => 'اللوت المسترجع إليه',
            'lines.*.returned_quantity' => 'الكمية المسترجعة',
        ];
    }
}
