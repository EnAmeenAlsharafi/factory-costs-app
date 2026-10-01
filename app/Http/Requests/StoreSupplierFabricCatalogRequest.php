<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierFabricCatalogRequest extends FormRequest
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
        $materialId = $this->integer('material_id');
        $supplierId = $this->integer('supplier_id');

        return [
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'catalog_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('supplier_fabric_catalogs')->where(
                    fn ($query) => $query
                        ->where('material_id', $materialId)
                        ->where('supplier_id', $supplierId)
                ),
            ],
            'catalog_name' => ['nullable', 'string', 'max:255'],
            'supplier_material_code' => ['nullable', 'string', 'max:100'],
            'catalog_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('material_id')) {
                    return;
                }

                $isFabric = Material::query()
                    ->whereKey($this->integer('material_id'))
                    ->whereHas('category', fn ($query) => $query->where('code', 'FABRIC'))
                    ->exists();

                if (! $isFabric) {
                    $validator->errors()->add('material_id', 'يمكن إضافة كتالوج مورد لمادة مصنفة كقماش فقط.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'material_id' => 'القماش',
            'supplier_id' => 'المورد',
            'catalog_number' => 'رقم الكتالوج لدى المورد',
            'catalog_name' => 'اسم الكتالوج',
            'supplier_material_code' => 'كود القماش لدى المورد',
            'catalog_image' => 'صورة الكتالوج',
            'is_active' => 'الحالة',
            'notes' => 'الملاحظات',
        ];
    }
}
