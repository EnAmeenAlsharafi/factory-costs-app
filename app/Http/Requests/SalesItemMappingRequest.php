<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesItemMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales_imports.manage');
    }

    public function rules(): array
    {
        return [
            'sales_channel_id' => 'required|exists:sales_channels,id',
            'match_type' => 'required|in:SKU,NAME',
            'match_value' => 'required|string|max:255',
            'target_type' => 'required|in:READY_STOCK,BUNDLE,MANUFACTURED',
            'material_id' => 'required_if:target_type,READY_STOCK|nullable|exists:materials,id',
            'bundle_definition_id' => 'required_if:target_type,BUNDLE|nullable|exists:sales_bundle_definitions,id',
            'product_model_id' => 'required_if:target_type,MANUFACTURED|nullable|exists:product_models,id',
            'is_active' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
