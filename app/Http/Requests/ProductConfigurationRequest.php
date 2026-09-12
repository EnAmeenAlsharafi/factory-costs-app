<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_model_id' => ['required', 'exists:product_models,id'],
            'standard_bed_size_id' => ['nullable', 'exists:standard_bed_sizes,id'],
            'width_cm' => ['required', 'numeric', 'gt:0'],
            'length_cm' => ['required', 'numeric', 'gt:0'],
            'has_storage' => ['nullable', 'boolean'],
            'configuration_name' => ['nullable', 'string', 'max:255'],
            'is_standard' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_model_id' => 'موديل المنتج',
            'width_cm' => 'العرض (سم)',
            'length_cm' => 'الطول (سم)',
            'has_storage' => 'خيار التخزين (سحارة/صندوق)',
        ];
    }
}
