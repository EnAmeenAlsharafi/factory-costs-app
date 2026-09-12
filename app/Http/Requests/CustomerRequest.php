<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->isMethod('POST') ? 'customers.create' : 'customers.update';

        return $this->user()?->can($action) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'customer_type_id' => ['required', 'exists:customer_types,id'],
            'default_sales_channel_id' => ['nullable', 'exists:sales_channels,id'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registration' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_credit_customer' => ['nullable', 'boolean'],
            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if (! $this->boolean('is_credit_customer') && $value > 0) {
                        $fail('لا يمكن تحديد سقف ائتماني لعميل غير آجل.');
                    }
                },
            ],
            'opening_balance' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم العميل.',
            'customer_type_id.required' => 'يرجى اختيار تصنيف العميل.',
            'customer_type_id.exists' => 'تصنيف العميل المختار غير صحيح.',
            'default_sales_channel_id.exists' => 'قناة البيع المختارة غير صالحة.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'credit_limit.min' => 'يجب ألا يقل السقف الائتماني عن صفر.',
        ];
    }
}
