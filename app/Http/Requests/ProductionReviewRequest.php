<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orders.approve_production') || $this->user()->can('orders.review_production');
    }

    public function rules(): array
    {
        return [
            'lines' => 'nullable|array',
            'lines.*.reference_width_cm' => 'nullable|numeric|gt:0',
            'lines.*.reference_length_cm' => 'nullable|numeric|gt:0',
            'lines.*.production_notes' => 'nullable|string',
            'production_notes' => 'nullable|string',
        ];
    }
}
