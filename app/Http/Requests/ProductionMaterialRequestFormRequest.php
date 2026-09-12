<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionMaterialRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.material_requests') ?? false;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => 'required|exists:production_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'requested_from_department_id' => 'nullable|exists:departments,id',
            'request_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.material_id' => 'required|exists:materials,id',
            'lines.*.fabric_color_id' => 'nullable|exists:fabric_colors,id',
            'lines.*.requested_quantity' => 'required|numeric|gt:0',
            'lines.*.approved_quantity' => 'nullable|numeric|gte:0',
            'lines.*.base_unit_id' => 'required|exists:units_of_measure,id',
            'lines.*.production_material_requirement_id' => 'nullable|exists:production_material_requirements,id',
            'lines.*.request_reason' => 'nullable|string|in:PLANNED_PRODUCTION,ADDITIONAL_REQUIREMENT,REWORK,REMANUFACTURE,CORRECTION,OTHER',
            'lines.*.notes' => 'nullable|string|max:500',
        ];
    }
}
