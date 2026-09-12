<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customer_types.manage') ?? false;
    }

    public function rules(): array
    {
        $typeId = $this->route('customer_type')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customer_types', 'code')->ignore($typeId),
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال الرمز التعريفي لنوع العميل.',
            'code.unique' => 'رمز نوع العميل هذا مستخدم مسبقاً.',
            'code.regex' => 'يجب أن يتكون الرمز من أحرف إنجليزية وأرقام وشرطات فقط.',
            'name_ar.required' => 'يرجى إدخال اسم نوع العميل بالعربية.',
        ];
    }
}
