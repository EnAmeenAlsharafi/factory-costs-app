<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FabricColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $colorId = $this->route('color') ? $this->route('color')->id : null;
        $materialId = $this->input('material_id');

        return [
            'material_id' => ['required', 'exists:materials,id'],
            'color_code' => ['required', 'string', 'max:50'],
            'color_name_ar' => ['required', 'string', 'max:100'],
            'color_name_en' => ['nullable', 'string', 'max:100'],
            'hex_code' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'pattern' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'material_id' => 'القماش الأساسي',
            'color_code' => 'كود اللون',
            'color_name_ar' => 'اسم اللون بالعربية',
            'color_name_en' => 'اسم اللون بالإنجليزية',
            'hex_code' => 'رمز اللون (HEX)',
            'pattern' => 'النقشة / النقش',
            'is_active' => 'الحالة',
            'notes' => 'ملاحظات',
        ];
    }
}
