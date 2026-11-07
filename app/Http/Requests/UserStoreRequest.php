<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isPortal = Role::find($this->input('role_id'))?->name === 'customer_portal';

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'department_id' => [Rule::prohibitedIf($isPortal), 'nullable', 'exists:departments,id'],
            'customer_id' => [Rule::requiredIf($isPortal), Rule::prohibitedIf(! $isPortal), 'nullable', Rule::exists('customers', 'id')->where('is_active', true), Rule::unique('users', 'customer_id')],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم الموظف الكامل.',
            'customer_id.required' => 'يجب ربط حساب بوابة العملاء بعميل مسجل.',
            'customer_id.unique' => 'يوجد حساب بوابة مرتبط بهذا العميل بالفعل.',
            'username.required' => 'يرجى إدخال اسم المستخدم.',
            'username.unique' => 'اسم المستخدم هذا مستخدم مسبقاً لموظف آخر.',
            'username.regex' => 'يجب أن يتكون اسم المستخدم من حروف إنجليزية وأرقام ونقاط أو شرطات فقط.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني هذا مسجل مسبقاً.',
            'role_id.required' => 'يرجى اختيار الدور الوظيفي للمستخدم.',
            'role_id.exists' => 'الدور الوظيفي المختار غير صالح.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 6 خانات.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $role = $this->filled('role_id') ? Role::find($this->input('role_id')) : null;

            if (! $role) {
                return;
            }

            if (($role->name === 'production_worker' || $role->isDepartmentRole()) && ! $this->filled('department_id')) {
                $validator->errors()->add('department_id', 'القسم التشغيلي إلزامي لموظفي الإنتاج.');

                return;
            }

            if ($role->isDepartmentRole() && (int) $this->input('department_id') !== $role->department_id) {
                $validator->errors()->add('role_id', 'الدور المختار يخص قسماً آخر، اختر دوراً من أدوار قسم الموظف.');
            }
        });
    }
}
