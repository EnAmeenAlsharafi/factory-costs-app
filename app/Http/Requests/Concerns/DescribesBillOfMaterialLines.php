<?php

namespace App\Http\Requests\Concerns;

/**
 * Plain-language validation messages for bill-of-materials lines (`items.*`), shared by the
 * recipe, recipe-version and template forms. Each message names the line by its position so it
 * reads well both in the page-level error list and next to the field in the line editor.
 */
trait DescribesBillOfMaterialLines
{
    /**
     * @return array<string, string>
     */
    public static function billOfMaterialLineMessages(): array
    {
        return [
            'items.required' => 'أضف بنداً واحداً على الأقل.',
            'items.min' => 'أضف بنداً واحداً على الأقل.',
            'items.*.material_id.required_if' => 'البند :position: اختر المادة الخام.',
            'items.*.material_id.exists' => 'البند :position: المادة الخام المختارة غير موجودة.',
            'items.*.semi_finished_component_id.required_if' => 'البند :position: اختر المكون نصف المصنع.',
            'items.*.semi_finished_component_id.exists' => 'البند :position: المكون المختار غير موجود.',
            'items.*.quantity.required' => 'البند :position: أدخل الكمية.',
            'items.*.quantity.numeric' => 'البند :position: الكمية يجب أن تكون رقماً.',
            'items.*.quantity.gt' => 'البند :position: الكمية يجب أن تكون أكبر من صفر.',
            'items.*.unit_id.required' => 'البند :position: اختر وحدة القياس.',
            'items.*.unit_id.exists' => 'البند :position: وحدة القياس غير موجودة.',
            'items.*.waste_percentage.numeric' => 'البند :position: نسبة الهدر يجب أن تكون رقماً.',
            'items.*.waste_percentage.min' => 'البند :position: نسبة الهدر بين 0 و100.',
            'items.*.waste_percentage.max' => 'البند :position: نسبة الهدر بين 0 و100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return static::billOfMaterialLineMessages();
    }
}
