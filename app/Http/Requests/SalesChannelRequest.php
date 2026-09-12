<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales_channels.manage') ?? false;
    }

    public function rules(): array
    {
        $channelId = $this->route('sales_channel')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sales_channels', 'code')->ignore($channelId),
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال الرمز التعريفي للقناة.',
            'code.unique' => 'رمز قناة البيع هذا مستخدم مسبقاً.',
            'code.regex' => 'يجب أن يتكون الرمز من أحرف إنجليزية وأرقام ونقاط أو شرطات فقط.',
            'name_ar.required' => 'يرجى إدخال اسم قناة البيع بالعربية.',
        ];
    }
}
