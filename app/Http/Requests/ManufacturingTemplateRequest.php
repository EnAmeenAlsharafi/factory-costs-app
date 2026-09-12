<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manufacturing_templates.manage');
    }

    public function rules(): array
    {
        return [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
