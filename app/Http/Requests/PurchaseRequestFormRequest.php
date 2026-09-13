<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchasing.request') ?? false;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'request_date' => ['nullable', 'date'],
            'required_by_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:NORMAL,URGENT,CRITICAL'],
            'source_type' => ['nullable', 'string'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.preferred_purchase_unit_id' => ['nullable', 'exists:units_of_measure,id'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.preferred_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'lines.*.justification' => ['nullable', 'string', 'max:1000'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
