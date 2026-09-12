<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperationProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('production.update_progress') || $this->user()->isAdministrator() || $this->user()->hasRole('production_manager') || $this->user()->hasRole('production_worker');
    }

    public function rules(): array
    {
        return [
            'added_quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
