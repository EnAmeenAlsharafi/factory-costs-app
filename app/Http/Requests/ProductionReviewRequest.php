<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'lines.*.point_category_id' => ['nullable', 'integer', 'exists:production_point_categories,id'],
            'lines.*.recipe_version_id' => ['nullable', 'integer', Rule::exists('manufacturing_recipe_versions', 'id')->where('status', 'APPROVED')],
            'production_notes' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lines.*.recipe_version_id.exists' => 'الوصفة المختارة غير معتمدة أو لم تعد موجودة.'];
    }
}
