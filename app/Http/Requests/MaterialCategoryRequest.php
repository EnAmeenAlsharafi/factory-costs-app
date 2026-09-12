<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('materials.manage') ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('material_category')?->id ?? $this->route('category')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('material_categories', 'code')->ignore($categoryId),
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال الرمز القياسي لفئة المواد بأحرف إنجليزية كبيرة.',
            'code.unique' => 'رمز الفئة هذا مستخدم مسبقاً.',
            'code.regex' => 'يجب أن يتكون الرمز من أحرف إنجليزية كبيرة وأرقام وشرطات فقط.',
            'name_ar.required' => 'يرجى إدخال اسم فئة المواد بالعربية.',
        ];
    }
}
