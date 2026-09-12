<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SemiFinishedComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('semi_finished_components.manage');
    }

    public function rules(): array
    {
        return [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'width_cm' => 'nullable|numeric|min:0',
            'length_cm' => 'nullable|numeric|min:0',
            'has_storage' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_storage' => $this->has('has_storage') ? $this->boolean('has_storage') : false,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
