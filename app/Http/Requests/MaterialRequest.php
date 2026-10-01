<?php

namespace App\Http\Requests;

use App\Models\MaterialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('materials.manage') ?? false;
    }

    public function rules(): array
    {
        $materialId = $this->route('material') ? $this->route('material')->id : null;

        $category = MaterialCategory::find($this->input('material_category_id'));
        $isFabric = $category?->code === 'FABRIC';

        $rules = [
            'code' => [$isFabric ? 'nullable' : 'required', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($materialId)],
            'name_ar' => [$isFabric ? 'nullable' : 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'material_category_id' => ['required', 'exists:material_categories,id'],
            'base_unit_id' => ['required', 'exists:units_of_measure,id'],
            'purchase_unit_id' => ['nullable', 'exists:units_of_measure,id'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];

        if ($category?->code === 'WOOD') {
            $rules['wood_type'] = ['required', 'string', 'max:100'];
            $rules['thickness_mm'] = ['required', 'numeric', 'gt:0'];
            $rules['width_cm'] = ['required', 'numeric', 'gt:0'];
            $rules['length_cm'] = ['required', 'numeric', 'gt:0'];
            $rules['grade'] = ['nullable', 'string', 'max:100'];
        } elseif ($category?->code === 'FOAM') {
            $rules['foam_type'] = ['required', 'string', 'max:100'];
            $rules['thickness_mm'] = ['required', 'numeric', 'gt:0'];
            $rules['density_kg_m3'] = ['nullable', 'numeric', 'gt:0'];
            $rules['hardness_rating'] = ['nullable', 'string', 'max:100'];
            $rules['width_cm'] = ['nullable', 'numeric', 'gt:0'];
            $rules['length_cm'] = ['nullable', 'numeric', 'gt:0'];
            $rules['block_dimensions'] = ['nullable', 'string', 'max:100'];
        } elseif ($category?->code === 'FABRIC') {
            $rules['supplier_id'] = ['required', 'exists:suppliers,id'];
            $rules['catalog_number'] = [
                'required',
                'string',
                'max:100',
                Rule::unique('fabric_material_specs', 'catalog_number')
                    ->where('supplier_id', $this->input('supplier_id'))
                    ->ignore($this->route('material')?->fabricSpec?->id),
            ];
            $rules['catalog_name'] = ['nullable', 'string', 'max:255'];
            $rules['catalog_image'] = ['nullable', 'image', 'max:5120'];
            $rules['fabric_type'] = ['required', 'string', 'max:100'];
            $rules['width_cm'] = ['required', 'numeric', 'gt:0'];
            $rules['pattern_type'] = ['nullable', 'string', 'max:100'];
            $rules['weight_gsm'] = ['nullable', 'numeric', 'gt:0'];
            $rules['composition'] = ['nullable', 'string', 'max:255'];
            $rules['martindale_rub_count'] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال كود المادة الخام.',
            'code.unique' => 'كود المادة مستخدم بالفعل.',
            'name_ar.required' => 'يرجى إدخال اسم المادة الخام بالعربية.',
            'material_category_id.required' => 'يرجى اختيار تصنيف المادة الخام.',
            'base_unit_id.required' => 'يرجى تحديد وحدة القياس الأساسية للمادة.',
            'purchase_unit_id.exists' => 'وحدة الشراء غير صالحة.',
            'wood_type.required' => 'يرجى إدخال نوع الخشب.',
            'thickness_mm.required' => 'يرجى تحديد سماكة الخشب/الإسفنج بالملليمتر.',
            'thickness_mm.gt' => 'يجب أن تكون السماكة أكبر من صفر.',
            'width_cm.required' => 'يرجى تحديد العرض بالسنتيمتر.',
            'width_cm.gt' => 'يجب أن يكون العرض أكبر من صفر.',
            'length_cm.required' => 'يرجى تحديد الطول بالسنتيمتر.',
            'length_cm.gt' => 'يجب أن يكون الطول أكبر من صفر.',
            'foam_type.required' => 'يرجى إدخال نوع الإسفنج.',
            'fabric_type.required' => 'يرجى إدخال نوع القماش.',
            'supplier_id.required' => 'يرجى اختيار مورد الكتالوج.',
            'catalog_number.required' => 'يرجى إدخال رقم الكتالوج لدى المورد.',
            'catalog_number.unique' => 'رقم الكتالوج هذا مسجل مسبقاً لدى هذا المورد.',
        ];
    }
}
