<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReadyStockProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('materials.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $material = $this->route('material');

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'has_ready_options' => ['nullable', 'boolean'],
            'ready_parent_id' => ['nullable', Rule::exists('materials', 'id')->where('has_ready_options', true)->whereNull('ready_parent_id')->where('is_sellable_ready_product', true)],
            'ready_option_label' => ['required_with:ready_parent_id', 'nullable', 'string', 'max:255'],
            'sales_display_name' => ['nullable', 'string', 'max:255'],
            'sales_image_url' => ['nullable', 'url:https', 'max:2048'],
            'sales_sku' => ['nullable', 'string', 'max:100', Rule::unique('materials', 'sales_sku')->ignore($material)],
            'salla_product_id' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:100', Rule::unique('materials', 'salla_product_id')->ignore($material)],
            'base_unit_id' => ['required', Rule::exists('units_of_measure', 'id')->where('is_active', true)],
            'sales_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'sales_reference_cost' => [Rule::excludeIf(! $this->user()->can('costing.view')), 'nullable', 'numeric', 'min:0', 'max:99999999999'],
            'salla_stock_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'salla_category' => ['nullable', 'string', 'max:255'],
            'salla_status' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $material = $this->route('material');
            if ($this->filled('ready_parent_id') && $this->boolean('has_ready_options')) {
                $validator->errors()->add('has_ready_options', 'الخيار يتبع منتجًا رئيسيًا ولا يمكن أن يحتوي خيارات أخرى.');
            }
            if ($material && (string) $material->ready_parent_id !== (string) $this->input('ready_parent_id')) {
                $validator->errors()->add('ready_parent_id', 'لا يمكن تغيير ارتباط الصنف بالمنتج الرئيسي بعد إنشائه.');
            }
            if ($material && ! $this->boolean('has_ready_options') && $material->readyOptions()->exists()) {
                $validator->errors()->add('has_ready_options', 'هذا المنتج يحتوي خيارات مسجلة بالفعل.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name_ar.required' => 'يرجى إدخال اسم المنتج الجاهز.',
            'sales_sku.unique' => 'رمز SKU مستخدم بالفعل لمنتج آخر.',
            'salla_product_id.unique' => 'رقم منتج سلة مسجل بالفعل لمنتج آخر.',
            'salla_product_id.regex' => 'رقم منتج سلة يجب أن يتكون من أرقام فقط.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['sales_sku', 'salla_product_id'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }
            $this->merge([$field => $value]);
        }
    }
}
