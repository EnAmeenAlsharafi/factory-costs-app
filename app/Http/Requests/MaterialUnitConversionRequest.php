<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterialUnitConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', 'exists:materials,id'],
            'from_unit_id' => ['required', 'exists:units_of_measure,id'],
            'to_unit_id' => ['required', 'exists:units_of_measure,id', 'different:from_unit_id'],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'material_id' => 'المادة / الخامة',
            'from_unit_id' => 'من وحدة',
            'to_unit_id' => 'إلى وحدة',
            'conversion_factor' => 'معامل التحويل',
            'notes' => 'ملاحظات',
        ];
    }
}
