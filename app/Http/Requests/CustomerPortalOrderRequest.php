<?php

namespace App\Http\Requests;

use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerPortalOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $submission = $this->route('submission');
        if ($this->routeIs('sales.portal-requests.offer')) {
            return $user && ! $user->isCustomerAccount() && $user->can('orders.update');
        }
        if ($user?->hasRole('customer_portal') && $submission && $submission->customer_id !== $user->customer_id) {
            abort(404);
        }

        return $user?->hasRole('customer_portal') && $user->customer?->is_active
            && (! $submission || $submission->customer_id === $user->customer_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $offer = $this->routeIs('sales.portal-requests.offer');

        return [
            'revision' => [$this->route('submission') ? 'required' : 'nullable', 'integer', 'min:1'],
            'customer_reference' => ['nullable', 'string', 'max:255'],
            'requested_delivery_date' => ['nullable', 'date'],
            'delivery_city' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:3000'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.line_type' => ['required', 'in:MANUFACTURED,READY_STOCK'],
            'lines.*.product_model_id' => ['nullable', Rule::exists('product_models', 'id')->where('is_active', true)],
            'lines.*.line_key' => ['nullable', 'uuid', 'distinct'],
            'lines.*.custom_design' => ['nullable', 'boolean'],
            'lines.*.custom_design_name' => ['required_if:lines.*.custom_design,1,true', 'nullable', 'string', 'max:255'],
            'lines.*.custom_design_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'lines.*.requested_fabric' => ['nullable', 'string', 'max:1000'],
            'lines.*.requested_color' => ['nullable', 'string', 'max:500'],
            'lines.*.material_id' => ['required_if:lines.*.line_type,READY_STOCK', 'nullable', Rule::exists('materials', 'id')->where('is_active', true)->where('is_sellable_ready_product', true)->where('has_ready_options', false)],
            'lines.*.requested_width_cm' => ['required_if:lines.*.line_type,MANUFACTURED', 'nullable', 'numeric', 'gt:0', 'max:1000'],
            'lines.*.requested_length_cm' => ['required_if:lines.*.line_type,MANUFACTURED', 'nullable', 'numeric', 'gt:0', 'max:1000'],
            'lines.*.has_storage' => ['nullable', 'boolean'],
            'lines.*.fabric_material_id' => ['nullable', Rule::exists('materials', 'id')->where('is_active', true)],
            'lines.*.fabric_color_id' => ['nullable', 'exists:fabric_colors,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'lines.*.product_configuration_id' => ['nullable', Rule::exists('product_configurations', 'id')->where('is_active', true)],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
            'lines.*.unit_price' => [$offer ? 'required' : 'exclude', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'lines.*.discount_amount' => [$offer ? 'nullable' : 'exclude', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'promised_delivery_date' => [$offer ? 'required' : 'exclude', 'date', 'after_or_equal:today'],
            'payment_terms_type' => [$offer ? 'required' : 'exclude', 'in:FULL_BEFORE_PRODUCTION,DEPOSIT_AND_BALANCE,CASH_ON_DELIVERY,CREDIT,CUSTOM'],
            'deposit_required_amount' => [$offer ? 'nullable' : 'exclude', 'required_if:payment_terms_type,DEPOSIT_AND_BALANCE', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'payment_due_date' => [$offer ? 'nullable' : 'exclude', 'required_if:payment_terms_type,CREDIT', 'date'],
            'public_response' => [$offer ? 'nullable' : 'exclude', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('lines', []) as $index => $line) {
                $custom = ! empty($line['custom_design']);
                if ($this->hasFile("lines.{$index}.custom_design_image") && (! $custom || ($line['line_type'] ?? '') !== 'MANUFACTURED')) {
                    $validator->errors()->add("lines.{$index}.custom_design_image", 'الصورة مخصصة لبنود التصميم الخاص فقط.');
                }
                if (($line['line_type'] ?? '') !== 'MANUFACTURED') {
                    continue;
                }
                if (! $custom && empty($line['product_model_id'])) {
                    $validator->errors()->add("lines.{$index}.product_model_id", 'اختر الموديل أو فعّل تصميم خاص حسب الطلب.');
                }
                $fabric = ! empty($line['fabric_material_id']) ? Material::with('category')->find($line['fabric_material_id']) : null;
                if ($fabric && $fabric->category?->code !== 'FABRIC') {
                    $validator->errors()->add("lines.{$index}.fabric_material_id", 'يرجى اختيار قماش من كتالوج الأقمشة.');
                }
                if (! empty($line['fabric_color_id']) && ! FabricColor::where('material_id', $line['fabric_material_id'] ?? null)->active()->available()->whereKey($line['fabric_color_id'])->exists()) {
                    $validator->errors()->add("lines.{$index}.fabric_color_id", 'اللون المختار لا يتبع القماش المحدد أو غير متاح.');
                }
                $requiresFabric = (! $custom && ProductModel::find($line['product_model_id'] ?? null)?->requires_fabric_selection)
                    || ! empty($line['requested_fabric']) || ! empty($line['requested_color']);
                if (! empty($line['product_configuration_id'])) {
                    $configuration = ProductConfiguration::find($line['product_configuration_id']);
                    if ($custom || $configuration->product_model_id !== (int) ($line['product_model_id'] ?? null) || $configuration->has_storage !== (bool) ($line['has_storage'] ?? false)) {
                        $validator->errors()->add("lines.{$index}.product_configuration_id", 'التهيئة المرجعية يجب أن تتبع الموديل وخيار التخزين المحددين.');
                    }
                }
                if ($this->routeIs('sales.portal-requests.offer') && $requiresFabric && (! $fabric || empty($line['fabric_color_id']))) {
                    $validator->errors()->add("lines.{$index}.fabric_color_id", 'يجب استكمال القماش واللون قبل إرسال العرض للعميل.');
                }
            }
        });
    }
}
