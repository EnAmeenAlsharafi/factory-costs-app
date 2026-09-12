<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    public function rules(): array
    {
        $modelId = $this->route('model')?->id ?? null;

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reference_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // Max 5MB
            'design_notes' => ['nullable', 'string', 'max:2000'],
            'is_custom_template' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar' => 'اسم الموديل بالعربية',
            'name_en' => 'اسم الموديل بالإنجليزية',
            'reference_image' => 'الصورة المرجعية',
            'design_notes' => 'ملاحظات التصميم',
        ];
    }
}
