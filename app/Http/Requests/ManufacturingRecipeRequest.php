<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturingRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recipes.manage');
    }

    public function rules(): array
    {
        return [
            'target_type' => 'required|in:PRODUCT_CONFIGURATION,SEMI_FINISHED_COMPONENT',
            'product_configuration_id' => 'required_if:target_type,PRODUCT_CONFIGURATION|nullable|exists:product_configurations,id',
            'semi_finished_component_id' => 'required_if:target_type,SEMI_FINISHED_COMPONENT|nullable|exists:semi_finished_components,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:MATERIAL,SEMI_FINISHED_COMPONENT',
            'items.*.material_id' => 'required_if:items.*.item_type,MATERIAL|nullable|exists:materials,id',
            'items.*.semi_finished_component_id' => 'required_if:items.*.item_type,SEMI_FINISHED_COMPONENT|nullable|exists:semi_finished_components,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_id' => 'required|exists:units_of_measure,id',
            'items.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
