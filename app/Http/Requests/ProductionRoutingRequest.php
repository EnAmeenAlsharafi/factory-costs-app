<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('production.manage_routing') || $this->user()->isAdministrator() || $this->user()->hasRole('production_manager');
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'operations' => ['required', 'array', 'min:1'],
            'operations.*.work_center_id' => ['required', 'exists:work_centers,id'],
            'operations.*.operation_code' => ['required', 'string', 'max:50'],
            'operations.*.name_ar' => ['required', 'string', 'max:255'],
            'operations.*.sequence_number' => ['required', 'integer', 'min:1'],
            'operations.*.branch_key' => ['nullable', 'string', 'max:50'],
            'operations.*.depends_on_indexes' => ['nullable', 'array'],
        ];
    }
}
