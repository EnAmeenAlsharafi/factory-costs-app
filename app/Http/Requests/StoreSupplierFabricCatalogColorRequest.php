<?php

namespace App\Http\Requests;

use App\Models\FabricColor;
use App\Models\SupplierFabricCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierFabricCatalogColorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $catalogId = $this->integer('supplier_fabric_catalog_id');

        return [
            'supplier_fabric_catalog_id' => ['required', 'integer', 'exists:supplier_fabric_catalogs,id'],
            'fabric_color_id' => [
                'required',
                'integer',
                'exists:fabric_colors,id',
                Rule::unique('supplier_fabric_catalog_colors')->where(
                    fn ($query) => $query->where('supplier_fabric_catalog_id', $catalogId)
                ),
            ],
            'supplier_color_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('supplier_fabric_catalog_colors')->where(
                    fn ($query) => $query->where('supplier_fabric_catalog_id', $catalogId)
                ),
            ],
            'supplier_color_name' => ['nullable', 'string', 'max:255'],
            'is_available' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['supplier_fabric_catalog_id', 'fabric_color_id'])) {
                    return;
                }

                $catalog = SupplierFabricCatalog::find($this->integer('supplier_fabric_catalog_id'));
                $fabricColor = FabricColor::find($this->integer('fabric_color_id'));

                if ($catalog?->material_id !== $fabricColor?->material_id) {
                    $validator->errors()->add(
                        'fabric_color_id',
                        'اللون الداخلي المختار لا يتبع القماش المرتبط بهذا الكتالوج.'
                    );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_fabric_catalog_id' => 'كتالوج المورد',
            'fabric_color_id' => 'رقم اللون الداخلي',
            'supplier_color_code' => 'كود اللون لدى المورد',
            'supplier_color_name' => 'اسم اللون لدى المورد',
            'is_available' => 'التوفر',
            'notes' => 'الملاحظات',
        ];
    }
}
