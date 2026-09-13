<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StandardBedSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') || $this->user()?->can('products.view');
    }

    public function rules(): array
    {
        $sizeId = $this->route('size')?->id ?? null;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:standard_bed_sizes,code,'.$sizeId],
            'width_cm' => ['required', 'numeric', 'gt:0'],
            'length_cm' => ['required', 'numeric', 'gt:0'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'رمز المقاس القياسي',
            'width_cm' => 'العرض (سم)',
            'length_cm' => 'الطول (سم)',
            'name_ar' => 'اسم المقاس (العربية)',
        ];
    }
}
