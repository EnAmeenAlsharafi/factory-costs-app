<?php

namespace App\Http\Requests;

use App\Models\TaskRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An assignee asking the task's author to excuse them, or for a later due date.
 */
class TaskChangeRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.use') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([TaskRequest::TYPE_DECLINE, TaskRequest::TYPE_EXTEND])],
            'reason' => ['required', 'string', 'max:1000'],
            'requested_due_date' => [
                Rule::requiredIf($this->input('type') === TaskRequest::TYPE_EXTEND),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'اكتب سبب الطلب ليعرفه من أسند المهمة.',
            'requested_due_date.required' => 'اختر التاريخ الجديد الذي تطلبه.',
            'requested_due_date.after_or_equal' => 'التاريخ المطلوب يجب أن يكون اليوم أو بعده.',
        ];
    }
}
