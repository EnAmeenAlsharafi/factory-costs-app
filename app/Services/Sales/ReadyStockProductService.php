<?php

namespace App\Services\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use Illuminate\Support\Facades\DB;

class ReadyStockProductService
{
    /** @param array<string, mixed> $data */
    public function save(array $data, ?Material $material = null): Material
    {
        return DB::transaction(function () use ($data, $material) {
            if (! empty($data['ready_parent_id'])) {
                $parent = Material::findOrFail($data['ready_parent_id']);
                $data['name_ar'] = mb_substr($parent->name_ar.' — '.$data['ready_option_label'], 0, 255);
                $data['sales_display_name'] = mb_substr(($parent->sales_display_name ?: $parent->name_ar).' — '.$data['ready_option_label'], 0, 255);
            }
            if ($material) {
                unset($data['base_unit_id']);
                $material->update($data);

                return $material;
            }

            return Material::create(array_merge($data, [
                'code' => Material::generateNextCode(),
                'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->firstOrFail()->id,
                'is_sellable_ready_product' => true,
            ]));
        });
    }
}
