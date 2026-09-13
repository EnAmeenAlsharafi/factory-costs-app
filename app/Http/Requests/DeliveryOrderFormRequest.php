<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryOrderFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delivery.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_order_id' => ['required', 'exists:customer_orders,id'],
            'scheduled_delivery_date' => ['nullable', 'date'],
            'scheduled_time_notes' => ['nullable', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'customer_name_snapshot' => ['required', 'string', 'max:255'],
            'customer_phone_snapshot' => ['nullable', 'string', 'max:50'],
            'city_snapshot' => ['nullable', 'string', 'max:100'],
            'district_snapshot' => ['nullable', 'string', 'max:100'],
            'delivery_address_snapshot' => ['nullable', 'string', 'max:1000'],
            'location_notes' => ['nullable', 'string', 'max:1000'],
            'installation_required' => ['nullable', 'boolean'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'installation_notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.production_order_id' => ['required', 'exists:production_orders,id'],
            'lines.*.customer_order_line_id' => ['nullable', 'exists:customer_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
