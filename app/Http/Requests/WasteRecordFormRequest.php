<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WasteRecordFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.record_waste') ?? false;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => 'required|exists:production_orders,id',
            'production_order_operation_id' => 'nullable|exists:production_order_operations,id',
            'quality_incident_id' => 'nullable|exists:quality_incidents,id',
            'material_id' => 'required|exists:materials,id',
            'fabric_color_id' => 'nullable|exists:fabric_colors,id',
            'inventory_lot_id' => 'nullable|exists:inventory_lots,id',
            'quantity' => 'required|numeric|gt:0',
            'unit_id' => 'required|exists:units_of_measure,id',
            'waste_reason_id' => 'required|exists:production_waste_reasons,id',
            'detected_department_id' => 'nullable|exists:departments,id',
            'responsible_department_id' => 'nullable|exists:departments,id',
            'occurred_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
