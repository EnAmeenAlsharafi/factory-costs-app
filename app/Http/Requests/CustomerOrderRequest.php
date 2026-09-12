<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orders.create') || $this->user()->can('orders.update');
    }

    public function rules(): array
    {
        return [
            'quotation_id' => 'nullable|exists:quotations,id',
            'customer_id' => 'required|exists:customers,id',
            'sales_channel_id' => 'required|exists:sales_channels,id',
            'customer_reference' => 'nullable|string|max:255',
            'external_order_reference' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'requested_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'priority' => 'required|in:NORMAL,URGENT,VIP',
            'commercial_notes' => 'nullable|string',
            'production_notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|integer',
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
