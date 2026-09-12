<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'production_date' => ['required', 'date_format:Y-m-d', 'unique:daily_reports,production_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.storage_type' => ['required', 'in:بدون تخزين,بتخزين'],
            'items.*.fabric_company_id' => ['nullable', 'exists:fabric_companies,id'],
            'items.*.fabric_type_id' => ['nullable', 'exists:fabric_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'production_date.required' => 'حقل تاريخ الإنتاج مطلوب.',
            'production_date.date_format' => 'صيغة تاريخ الإنتاج يجب أن تكون بصيغة يوم-شهر-سنة (YYYY-MM-DD).',
            'production_date.unique' => 'يوجد سجل إنتاج مسجل بهذا التاريخ بالفعل. يمكنك الانتقال إلى السجلات لمراجعته أو تعديله.',
            'items.required' => 'بيانات أصناف الإنتاج مطلوبة.',
            'items.*.product_id.required' => 'معرّف الصنف مطلوب.',
            'items.*.product_id.exists' => 'الصنف المحدد غير موجود في النظام.',
            'items.*.quantity.required' => 'حقل العدد مطلوب لجميع الأصناف.',
            'items.*.quantity.integer' => 'حقل العدد يجب أن يكون رقماً صحيحاً.',
            'items.*.quantity.min' => 'العدد لا يمكن أن يكون سالباً.',
            'items.*.storage_type.required' => 'حقل نوع التخزين مطلوب.',
            'items.*.storage_type.in' => 'نوع التخزين المحدد غير صالح (يجب أن يكون "بدون تخزين" أو "بتخزين").',
            'items.*.fabric_company_id.exists' => 'شركة القماش المحددة غير موجودة.',
            'items.*.fabric_type_id.exists' => 'نوع القماش المحدد غير موجود.',
        ];
    }
}
