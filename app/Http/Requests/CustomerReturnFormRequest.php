<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerReturnFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delivery.manage_returns') ?? false;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => ['required', 'exists:production_orders,id'],
            'quantity_returned' => ['required_without:quantity', 'nullable', 'numeric', 'gt:0'],
            'quantity' => ['required_without:quantity_returned', 'nullable', 'numeric', 'gt:0'],
            'reason_code' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'condition_code' => ['nullable', 'string', 'max:50'],
            'quality_incident_id' => ['nullable', 'exists:quality_incidents,id'],
        ];
    }
}
