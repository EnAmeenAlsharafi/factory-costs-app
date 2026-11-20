<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A task as written by its author: a quick personal to-do, or a task assigned to employees.
 * Who may be assigned is checked in TaskService against the author's permissions.
 */
class TaskFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.use') ?? false;
    }

    /**
     * The department and role pickers submit an empty «— بدون —» choice; drop it.
     */
    protected function prepareForValidation(): void
    {
        foreach (['assignee_ids', 'department_ids', 'role_ids'] as $field) {
            if (is_array($this->input($field))) {
                $this->merge([$field => array_values(array_filter($this->input($field), fn ($value) => $value !== null && $value !== ''))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'task_list_id' => ['nullable', 'integer'],
            'is_important' => ['nullable', 'boolean'],
            'my_day' => ['nullable', 'boolean'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'اكتب عنوان المهمة.',
            'title.max' => 'عنوان المهمة طويل؛ ضع التفاصيل في خانة التفاصيل.',
            'due_date.date' => 'تاريخ الاستحقاق غير صحيح.',
        ];
    }
}
