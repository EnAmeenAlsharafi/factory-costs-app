<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchasing.create_po') ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'supplier_quotation_id' => ['nullable', 'exists:supplier_quotations,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'shipping_amount' => ['nullable', 'numeric', 'gte:0'],
            'other_charges' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.purchase_unit_id' => ['required', 'exists:units_of_measure,id'],
            'lines.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.purchase_request_line_id' => ['nullable', 'exists:purchase_request_lines,id'],
            'lines.*.supplier_quotation_line_id' => ['nullable', 'exists:supplier_quotation_lines,id'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
