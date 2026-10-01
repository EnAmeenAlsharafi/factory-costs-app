<?php

namespace App\Http\Requests;

use App\Models\FabricColor;
use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabricColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('materials.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $color = $this->route('color');
        if (! $this->has('material_id')) {
            if ($color instanceof FabricColor) {
                $this->merge(['material_id' => $color->material_id]);
            } else {
                $material = $this->route('material') ?? $this->query('material') ?? $this->query('material_id');
                $materialId = $material instanceof Material ? $material->id : (is_numeric($material) ? (int) $material : null);
                if ($materialId) {
                    $this->merge(['material_id' => $materialId]);
                }
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $color = $this->route('color');
            if ($color instanceof FabricColor && $this->filled('material_id')) {
                // If the user payload explicitly submitted a different material_id, reject it
                $submittedMaterialId = (int) $this->input('material_id');
                if ($submittedMaterialId !== (int) $color->material_id) {
                    $validator->errors()->add('material_id', 'لا يمكن نقل اللون إلى مادة قماش أخرى.');
                }
            }
        });
    }

    public function rules(): array
    {
        $color = $this->route('color');
        $colorId = $color instanceof FabricColor ? $color->id : (is_numeric($color) ? $color : null);
        $materialId = ($color instanceof FabricColor) ? $color->material_id : $this->input('material_id');

        return [
            'material_id' => ['required', 'exists:materials,id'],
            'color_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fabric_colors', 'color_code')
                    ->where('material_id', $materialId)
                    ->ignore($colorId),
            ],
            'supplier_color_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fabric_colors', 'supplier_color_code')
                    ->where('material_id', $materialId)
                    ->ignore($colorId),
            ],
            'color_name_ar' => ['nullable', 'string', 'max:100'],
            'color_name_en' => ['nullable', 'string', 'max:100'],
            'hex_code' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'pattern' => ['nullable', 'string', 'max:100'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'material_id' => 'القماش الأساسي',
            'color_code' => 'الرقم الداخلي للون',
            'supplier_color_code' => 'كود لون المورد',
            'color_name_ar' => 'اسم اللون بالعربية',
            'color_name_en' => 'اسم اللون بالإنجليزية',
            'hex_code' => 'رمز اللون (HEX)',
            'pattern' => 'النقشة / النقش',
            'is_available' => 'التوفر',
            'is_active' => 'الحالة',
            'notes' => 'ملاحظات',
        ];
    }

    public function messages(): array
    {
        return [
            'color_code.required' => 'يرجى إدخال الرقم الداخلي للون.',
            'color_code.unique' => 'الرقم الداخلي لهذا اللون مستخدم بالفعل داخل هذا القماش.',
            'supplier_color_code.required' => 'يرجى إدخال كود اللون المعتمد لدى المورد.',
            'supplier_color_code.unique' => 'كود لون المورد مستخدم بالفعل داخل هذا القماش.',
        ];
    }
}
