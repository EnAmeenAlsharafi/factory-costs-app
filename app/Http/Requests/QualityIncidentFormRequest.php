<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProductionDepartmentScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QualityIncidentFormRequest extends FormRequest
{
    use ValidatesProductionDepartmentScope;

    public function authorize(): bool
    {
        return $this->user()?->can('production.report_quality') ?? false;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => 'required|exists:production_orders,id',
            'production_order_operation_id' => 'nullable|exists:production_order_operations,id',
            'affected_quantity' => 'required|integer|min:1',
            'detected_department_id' => 'required|exists:departments,id',
            'responsible_department_id' => 'nullable|exists:departments,id',
            'incident_type' => 'required|string|in:WRONG_DIMENSION,WRONG_FABRIC,WRONG_COLOR,MATERIAL_DAMAGE,WORKMANSHIP_DEFECT,LOADING_DAMAGE,MISSING_COMPONENT,ASSEMBLY_ERROR,OTHER',
            'description' => 'required|string|max:2000',
            'severity' => 'required|string|in:LOW,MEDIUM,HIGH,CRITICAL',
            'disposition' => 'nullable|string|in:REPAIR,REWORK,REMANUFACTURE,SCRAP,ACCEPT_AS_IS',
            'disposition_notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateProductionDepartmentScope($validator);

            if ($this->filled('disposition') && ! $this->user()?->can('production.manage_quality')) {
                $validator->errors()->add('disposition', 'لا تملك صلاحية اتخاذ قرار معالجة الجودة عند إنشاء البلاغ. سجّل البلاغ أولاً ثم اطلب من مسؤول الجودة اتخاذ القرار.');
            }
        });
    }
}
