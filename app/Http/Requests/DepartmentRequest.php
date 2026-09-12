<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departments.manage') ?? false;
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->ignore($departmentId),
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_production_department' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال رمز القسم بالأحرف الإنجليزية الكبيرة.',
            'code.unique' => 'رمز القسم هذا مستخدم مسبقاً.',
            'code.regex' => 'يجب كتابة رمز القسم بأحرف إنجليزية كبيرة وأرقام فقط.',
            'name_ar.required' => 'يرجى إدخال مسمى القسم بالعربية.',
        ];
    }
}
