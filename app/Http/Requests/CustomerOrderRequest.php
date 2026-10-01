<?php

namespace App\Http\Requests;

use App\Models\FabricColor;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use Illuminate\Foundation\Http\FormRequest;

class CustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orders.create') || $this->user()->can('orders.update');
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('customer_po_number') && ! $this->filled('external_order_reference')) {
            $input['external_order_reference'] = $this->input('customer_po_number');
        }

        if ($this->filled('promised_delivery_date') && ! $this->filled('requested_delivery_date')) {
            $input['requested_delivery_date'] = $this->input('promised_delivery_date');
        }

        if ($this->filled('notes') && ! $this->filled('commercial_notes')) {
            $input['commercial_notes'] = $this->input('notes');
        }

        if (! $this->filled('priority')) {
            $input['priority'] = 'NORMAL';
        }

        $lines = $this->input('lines', []);
        if (is_array($lines)) {
            foreach ($lines as $i => $line) {
                if (! empty($line['product_configuration_id']) && (empty($line['requested_width_cm']) || empty($line['requested_length_cm']))) {
                    $config = ProductConfiguration::find($line['product_configuration_id']);
                    if ($config) {
                        $lines[$i]['requested_width_cm'] = ! empty($line['requested_width_cm']) ? $line['requested_width_cm'] : $config->width_cm;
                        $lines[$i]['requested_length_cm'] = ! empty($line['requested_length_cm']) ? $line['requested_length_cm'] : $config->length_cm;
                    }
                }

                // Auto-infer supplier from fabric material (strictly from fabric_material_specs) if not provided
                if (! empty($line['fabric_material_id']) && empty($line['fabric_supplier_id'])) {
                    $material = Material::with('fabricSpec')->find($line['fabric_material_id']);
                    if ($material?->fabricSpec?->supplier_id) {
                        $lines[$i]['fabric_supplier_id'] = $material->fabricSpec->supplier_id;
                    }
                }

                // Auto-populate codes and supplier strictly from color ID in database (anti-tampering)
                if (! empty($line['fabric_color_id'])) {
                    $color = FabricColor::with('material.fabricSpec')->find($line['fabric_color_id']);
                    if ($color) {
                        $lines[$i]['fabric_color_code'] = $color->color_code;
                        $lines[$i]['fabric_supplier_color_code'] = $color->supplier_color_code;
                        if ($color->material?->fabricSpec?->supplier_id) {
                            $lines[$i]['fabric_supplier_id'] = $color->material->fabricSpec->supplier_id;
                        }
                    }
                }
            }
            $input['lines'] = $lines;
        }

        if (! empty($input)) {
            $this->merge($input);
        }
    }

    public function rules(): array
    {
        return [
            'quotation_id' => 'nullable|exists:quotations,id',
            'customer_id' => 'required|exists:customers,id',
            'sales_channel_id' => 'required|exists:sales_channels,id',
            'customer_reference' => 'nullable|string|max:255',
            'external_order_reference' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'requested_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'priority' => 'required|in:NORMAL,URGENT,VIP',
            'payment_terms_type' => 'nullable|in:FULL_BEFORE_PRODUCTION,DEPOSIT_AND_BALANCE,CASH_ON_DELIVERY,CREDIT,CUSTOM',
            'deposit_required_amount' => 'nullable|numeric|min:0',
            'deposit_required_percent' => 'nullable|numeric|min:0|max:100',
            'payment_due_date' => 'nullable|date',
            'credit_days' => 'nullable|integer|min:0',
            'commercial_notes' => 'nullable|string',
            'production_notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|integer',
            'lines.*.custom_design' => 'nullable|boolean',
            'lines.*.custom_design_name' => 'required_if:lines.*.custom_design,1,true|nullable|string|max:255',
            'lines.*.product_model_id' => 'nullable|exists:product_models,id',
            'lines.*.product_configuration_id' => 'nullable|exists:product_configurations,id',
            'lines.*.customer_product_alias_id' => 'nullable|exists:customer_product_aliases,id',
            'lines.*.requested_width_cm' => 'required|numeric|gt:0',
            'lines.*.requested_length_cm' => 'required|numeric|gt:0',
            'lines.*.reference_width_cm' => 'nullable|numeric|gt:0',
            'lines.*.reference_length_cm' => 'nullable|numeric|gt:0',
            'lines.*.fabric_supplier_id' => 'nullable|exists:suppliers,id',
            'lines.*.fabric_material_id' => 'nullable|exists:materials,id',
            'lines.*.fabric_color_id' => 'nullable|exists:fabric_colors,id',
            'lines.*.fabric_color_code' => 'nullable|string|max:100',
            'lines.*.fabric_supplier_color_code' => 'nullable|string|max:100',
            'lines.*.fabric_notes' => 'nullable|string',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_amount' => 'nullable|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
            'lines.*.production_notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);
            foreach ($lines as $index => $line) {
                $requiresFabric = false;
                if (! empty($line['product_model_id'])) {
                    $model = ProductModel::find($line['product_model_id']);
                    if ($model && $model->requires_fabric_selection) {
                        $requiresFabric = true;
                    }
                } elseif (! empty($line['custom_design']) && (! isset($line['requires_fabric']) || ! empty($line['requires_fabric']))) {
                    if (! empty($line['fabric_supplier_id']) || ! empty($line['fabric_material_id']) || ! empty($line['fabric_color_code']) || ! empty($line['requires_fabric'])) {
                        $requiresFabric = true;
                    }
                }

                if ($requiresFabric) {
                    if (empty($line['fabric_supplier_id'])) {
                        $validator->errors()->add("lines.{$index}.fabric_supplier_id", 'يرجى اختيار مورد القماش لهذا البند أو اختيار مادة مرتبطة بمورد.');
                    }
                    if (empty($line['fabric_material_id'])) {
                        $validator->errors()->add("lines.{$index}.fabric_material_id", 'يرجى اختيار مادة القماش.');
                    }
                    if (empty($line['fabric_color_id']) && empty($line['fabric_color_code'])) {
                        $validator->errors()->add("lines.{$index}.fabric_color_code", 'يرجى اختيار لون القماش المعتمد.');
                    }
                }

                if (! empty($line['fabric_material_id'])) {
                    $material = Material::with(['category', 'fabricSpec'])->find($line['fabric_material_id']);
                    if ($material && strtoupper($material->category?->code) !== 'FABRIC') {
                        $validator->errors()->add("lines.{$index}.fabric_material_id", 'نوع المادة المختار ليس من فئة الأقمشة.');
                    }

                    // Requirement 7: Prevent using incomplete legacy fabrics without supplier or catalog
                    $hasSupplier = ! empty($material->fabricSpec?->supplier_id) || $material->suppliers()->exists();
                    $hasCatalog = ! empty($material->fabricSpec?->catalog_number);
                    $isIncompleteLegacy = in_array($material->code, ['MAT-000014', 'MAT-000016', 'MAT-000017'], true) || (! $hasSupplier && ! $hasCatalog);

                    if ($material && (! $material->is_active || $isIncompleteLegacy)) {
                        $validator->errors()->add("lines.{$index}.fabric_material_id", 'لا يمكن استخدام هذا القماش في طلب جديد لعدم اكتمال بيانات المورد ورقم الكتالوج.');
                    }

                    // Requirement 2.7: Check supplier-material linkage
                    if (! empty($line['fabric_supplier_id']) && $material) {
                        $supplierId = (int) $line['fabric_supplier_id'];
                        $isLinked = ((int) $material->fabricSpec?->supplier_id === $supplierId)
                            || $material->suppliers()->where('suppliers.id', $supplierId)->exists();
                        if (! $isLinked) {
                            $validator->errors()->add("lines.{$index}.fabric_supplier_id", 'المورد المختار غير مرتبط بقماش هذا البند.');
                        }
                    }

                    if (! empty($line['fabric_color_id'])) {
                        $color = FabricColor::find($line['fabric_color_id']);
                        if (! $color || (int) $color->material_id !== (int) $line['fabric_material_id']) {
                            $validator->errors()->add("lines.{$index}.fabric_color_id", 'اللون المحدد لا يتبع مادة القماش المختارة.');
                        } elseif (! $color->is_active || ! $color->is_available) {
                            $validator->errors()->add("lines.{$index}.fabric_color_id", 'اللون المحدد غير متوفر أو غير نشط حالياً.');
                        }
                    }
                }
            }
        });
    }
}
