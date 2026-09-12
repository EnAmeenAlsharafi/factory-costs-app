<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('units.manage') ?? false;
    }

    public function rules(): array
    {
        $unitId = $this->route('units_of_measure')?->id ?? $this->route('unit')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units_of_measure', 'code')->ignore($unitId),
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'unit_type' => ['nullable', 'string', 'max:50'],
            'allows_decimal' => ['nullable', 'boolean'],
            'decimal_precision' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال الرمز القياسي للوحدة بالأحرف الإنجليزية الكبيرة.',
            'code.unique' => 'رمز وحدة القياس هذا مستخدم مسبقاً.',
            'code.regex' => 'يجب كتابة رمز الوحدة بأحرف إنجليزية كبيرة وأرقام فقط.',
            'name_ar.required' => 'يرجى إدخال اسم وحدة القياس بالعربية.',
        ];
    }
}
