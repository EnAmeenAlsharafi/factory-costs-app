<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerProductAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') || $this->user()?->can('products.view');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'product_model_id' => ['required', 'exists:product_models,id'],
            'customer_product_name' => ['required', 'string', 'max:255'],
            'customer_product_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'العميل',
            'product_model_id' => 'الموديل الداخلي',
            'customer_product_name' => 'اسم المنتج لدى العميل',
            'customer_product_code' => 'كود المنتج لدى العميل',
        ];
    }
}
