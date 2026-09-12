<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotations.create') || $this->user()->can('quotations.update');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'sales_channel_id' => 'required|exists:sales_channels,id',
            'quotation_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quotation_date',
            'notes' => 'nullable|string',
            'commercial_notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.custom_design' => 'nullable|boolean',
            'lines.*.custom_design_name' => 'required_if:lines.*.custom_design,1,true|nullable|string|max:255',
            'lines.*.product_model_id' => 'nullable|exists:product_models,id',
            'lines.*.product_configuration_id' => 'nullable|exists:product_configurations,id',
            'lines.*.customer_product_alias_id' => 'nullable|exists:customer_product_aliases,id',
            'lines.*.requested_width_cm' => 'required|numeric|gt:0',
            'lines.*.requested_length_cm' => 'required|numeric|gt:0',
            'lines.*.reference_width_cm' => 'nullable|numeric|gt:0',
            'lines.*.reference_length_cm' => 'nullable|numeric|gt:0',
            'lines.*.has_storage' => 'nullable|boolean',
            'lines.*.fabric_material_id' => 'nullable|exists:materials,id',
            'lines.*.fabric_color_id' => 'nullable|exists:fabric_colors,id',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_amount' => 'nullable|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
            'lines.*.production_notes' => 'nullable|string',
        ];
    }
}
