<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesBundleDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales_imports.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sales_sku' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'components' => 'required|array|min:1',
            'components.*.component_type' => 'required|in:MANUFACTURED,READY_STOCK',
            'components.*.product_model_id' => 'required_if:components.*.component_type,MANUFACTURED|nullable|exists:product_models,id',
            'components.*.product_configuration_id' => 'required_if:components.*.component_type,MANUFACTURED|nullable|exists:product_configurations,id',
            'components.*.material_id' => 'required_if:components.*.component_type,READY_STOCK|nullable|exists:materials,id',
            'components.*.quantity' => 'required|numeric|gt:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
