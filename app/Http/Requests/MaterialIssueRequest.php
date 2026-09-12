<?php

namespace App\Http\Requests;

use App\Models\InventoryLot;
use Illuminate\Foundation\Http\FormRequest;

class MaterialIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.issue') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rawLines = $this->input('lines', $this->input('items', []));

        if (is_array($rawLines)) {
            $normalized = array_map(function ($item) {
                $lotId = $item['inventory_lot_id'] ?? ($item['lot_id'] ?? null);
                $lot = $lotId ? InventoryLot::find($lotId) : null;

                return [
                    'inventory_lot_id' => $lotId,
                    'material_id' => $item['material_id'] ?? ($lot?->material_id ?? null),
                    'fabric_color_id' => $item['fabric_color_id'] ?? ($lot?->fabric_color_id ?? null),
                    'issued_quantity' => $item['issued_quantity'] ?? ($item['quantity'] ?? null),
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
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.inventory_lot_id' => ['required', 'exists:inventory_lots,id'],
            'lines.*.issued_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_id' => 'المستودع',
            'issue_date' => 'تاريخ الصرف',
            'lines' => 'بنود الصرف',
            'lines.*.material_id' => 'المادة الخام',
            'lines.*.inventory_lot_id' => 'اللوت المصروف منه',
            'lines.*.issued_quantity' => 'الكمية المصروفة',
        ];
    }
}
