<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateManufacturingRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recipes.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_type' => 'required|in:PRODUCT_CONFIGURATION,SEMI_FINISHED_COMPONENT',
            'product_configuration_id' => 'required_if:target_type,PRODUCT_CONFIGURATION|nullable|exists:product_configurations,id',
            'semi_finished_component_id' => 'required_if:target_type,SEMI_FINISHED_COMPONENT|nullable|exists:semi_finished_components,id',
            'is_active' => 'nullable|boolean',
        ];
    }
}
