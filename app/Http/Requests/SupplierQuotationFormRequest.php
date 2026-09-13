<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierQuotationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchasing.manage_quotes') ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_rfq_id' => ['nullable', 'exists:purchase_rfqs,id'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'shipping_amount' => ['nullable', 'numeric', 'gte:0'],
            'other_charges' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.quoted_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.purchase_unit_id' => ['required', 'exists:units_of_measure,id'],
            'lines.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.supplier_material_code' => ['nullable', 'string', 'max:100'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
