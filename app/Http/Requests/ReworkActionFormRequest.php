<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReworkActionFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.manage_rework') ?? false;
    }

    public function rules(): array
    {
        return [
            'quality_incident_id' => 'required|exists:quality_incidents,id',
            'action_type' => 'required|string|in:REPAIR,REWORK,REMAKE_COMPONENT,REMANUFACTURE',
            'quantity' => 'required|integer|min:1',
            'assigned_department_id' => 'required|exists:departments,id',
            'source_operation_id' => 'nullable|exists:production_order_operations,id',
            'target_operation_id' => 'nullable|exists:production_order_operations,id',
            'block_operation' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
