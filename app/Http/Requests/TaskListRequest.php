<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.use') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اكتب اسم القائمة.',
        ];
    }
}
