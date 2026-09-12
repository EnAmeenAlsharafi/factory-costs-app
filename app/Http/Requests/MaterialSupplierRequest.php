<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterialSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', 'exists:materials,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_item_code' => ['nullable', 'string', 'max:100'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'minimum_order_qty' => ['nullable', 'numeric', 'min:0'],
            'is_preferred' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'material_id' => 'المادة / الخامة',
            'supplier_id' => 'المورد',
            'supplier_item_code' => 'كود المادة لدى المورد',
            'lead_time_days' => 'مدة التوريد (بالأيام)',
            'minimum_order_qty' => 'الحد الأدنى للطلب',
            'is_preferred' => 'مورد مفضل',
            'notes' => 'ملاحظات',
        ];
    }
}
