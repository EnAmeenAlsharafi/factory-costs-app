<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DepartmentRoleStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('roles.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_production_department', true)],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'copy_from_role_id' => ['nullable', 'exists:roles,id'],
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
            'department_id.required' => 'يجب تحديد القسم الإنتاجي الذي يتبع له الدور.',
            'department_id.exists' => 'الأدوار المنفصلة متاحة للأقسام الإنتاجية فقط.',
            'display_name.required' => 'يرجى إدخال اسم الدور.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('copy_from_role_id')) {
                return;
            }

            $source = Role::find($this->input('copy_from_role_id'));

            if ($source && $source->department_id !== (int) $this->input('department_id')) {
                $validator->errors()->add('copy_from_role_id', 'يمكن النسخ من أدوار نفس القسم فقط.');
            }
        });
    }
}
