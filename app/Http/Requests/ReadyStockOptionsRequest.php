<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReadyStockOptionsRequest extends FormRequest
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
        return [
            'options' => ['required', 'array', 'min:1', 'max:100'],
            'options.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('materials', 'id')->where('ready_parent_id', $this->route('material')->id)],
            'options.*.label' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'options.*.sales_sku' => ['nullable', 'string', 'max:100', 'distinct:ignore_case'],
            'options.*.sales_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'options.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('options', []) as $index => $option) {
                $query = Material::where('ready_parent_id', $this->route('material')->id)->where('ready_option_label', $option['label']);
                if (! empty($option['id'])) {
                    $query->whereKeyNot($option['id']);
                }
                if ($query->exists()) {
                    $validator->errors()->add("options.{$index}.label", 'هذا الخيار مسجل بالفعل لهذا المنتج.');
                }
                if (! empty($option['sales_sku']) && Material::where('sales_sku', $option['sales_sku'])->when(! empty($option['id']), fn ($query) => $query->whereKeyNot($option['id']))->exists()) {
                    $validator->errors()->add("options.{$index}.sales_sku", 'رمز SKU مستخدم بالفعل لصنف آخر.');
                }
            }
        });
    }
}
