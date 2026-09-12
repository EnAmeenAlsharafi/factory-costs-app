<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('production.release') || $this->user()->isAdministrator() || $this->user()->hasRole('production_manager');
    }

    public function rules(): array
    {
        return [
            'customer_order_line_id' => ['required', 'exists:customer_order_lines,id'],
            'released_quantity' => ['required', 'integer', 'min:1'],
            'manufacturing_recipe_version_id' => ['nullable', 'exists:manufacturing_recipe_versions,id'],
            'production_routing_id' => ['nullable', 'exists:production_routings,id'],
            'priority' => ['required', 'in:NORMAL,URGENT,VIP'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_completion_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'production_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
