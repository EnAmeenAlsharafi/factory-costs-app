<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionMaterialRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.material_requests') ?? false;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => 'required|exists:production_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'requested_from_department_id' => 'nullable|exists:departments,id',
            'request_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.material_id' => 'required|exists:materials,id',
            'lines.*.fabric_color_id' => 'nullable|exists:fabric_colors,id',
            'lines.*.requested_quantity' => 'required|numeric|gt:0',
            'lines.*.approved_quantity' => 'nullable|numeric|gte:0',
            'lines.*.base_unit_id' => 'required|exists:units_of_measure,id',
            'lines.*.production_material_requirement_id' => 'nullable|exists:production_material_requirements,id',
            'lines.*.request_reason' => 'nullable|string|in:PLANNED_PRODUCTION,ADDITIONAL_REQUIREMENT,REWORK,REMANUFACTURE,CORRECTION,OTHER',
            'lines.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'اختر المستودع الموجه له الطلب.',
            'request_date.required' => 'حدد تاريخ الطلب.',
            'lines.required' => 'أضف خامة واحدة على الأقل للطلب.',
            'lines.min' => 'أضف خامة واحدة على الأقل للطلب.',
            'lines.*.material_id.required' => 'اختر الخامة في كل سطر.',
            'lines.*.base_unit_id.required' => 'الخامة المختارة ليس لها وحدة قياس أساسية.',
            'lines.*.requested_quantity.required' => 'أدخل الكمية المطلوبة في كل سطر.',
            'lines.*.requested_quantity.gt' => 'الكمية المطلوبة يجب أن تكون أكبر من صفر.',
        ];
    }
}
