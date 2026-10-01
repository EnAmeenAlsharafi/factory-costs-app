<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('supplier_fabric_catalogs')) {
            return;
        }

        $fabricCategory = DB::table('material_categories')->where('code', 'FABRIC')->first();
        $meterUnit = DB::table('units_of_measure')->where('code', 'METER')->first()
            ?? DB::table('units_of_measure')->first();

        if (! $fabricCategory || ! $meterUnit) {
            return;
        }

        // Group legacy catalogs by material_id
        $catalogsByMaterial = DB::table('supplier_fabric_catalogs')
            ->whereNotNull('material_id')
            ->orderBy('id')
            ->get()
            ->groupBy('material_id');

        foreach ($catalogsByMaterial as $materialId => $catalogs) {
            if ($catalogs->count() <= 1) {
                continue;
            }

            // The first catalog retains the primary material
            $firstCatalog = $catalogs->first();

            // Any subsequent catalog pointing to the same material becomes an independent material
            foreach ($catalogs->slice(1) as $cat) {
                $supplier = DB::table('suppliers')->where('id', $cat->supplier_id)->first();
                if (! $supplier) {
                    throw new RuntimeException("تعذر العثور على مورد الكتالوج [{$cat->catalog_number}].");
                }

                // Check if an independent material already exists for this supplier + catalog_number
                $existingSpec = DB::table('fabric_material_specs')
                    ->where('supplier_id', $supplier->id)
                    ->where('catalog_number', (string) $cat->catalog_number)
                    ->first();

                if ($existingSpec && (int) $existingSpec->material_id !== (int) $materialId) {
                    $newMaterialId = $existingSpec->material_id;
                } else {
                    // Generate unique code for new material
                    $newCode = $this->generateUniqueMaterialCode();
                    $fabricType = 'شانيل';

                    $baseSpec = DB::table('fabric_material_specs')->where('material_id', $materialId)->first();
                    if ($baseSpec && ! empty($baseSpec->fabric_type)) {
                        $fabricType = $baseSpec->fabric_type;
                    }

                    $standardName = "قماش {$fabricType} - {$supplier->name} - {$cat->catalog_number}";

                    $newMaterialId = DB::table('materials')->insertGetId([
                        'code' => $newCode,
                        'name_ar' => $standardName,
                        'name_en' => null,
                        'material_category_id' => $fabricCategory->id,
                        'base_unit_id' => $meterUnit->id,
                        'purchase_unit_id' => $meterUnit->id,
                        'min_stock_level' => 0,
                        'reorder_point' => 0,
                        'is_active' => true,
                        'notes' => 'تم إنشاؤها عبر الترحيل التصحيحي لفصل الكتالوجات القديمة المتعددة.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('fabric_material_specs')->insert([
                        'material_id' => $newMaterialId,
                        'supplier_id' => $supplier->id,
                        'catalog_number' => (string) $cat->catalog_number,
                        'catalog_name' => $cat->catalog_name ?: $standardName,
                        'catalog_image_path' => $cat->image_path,
                        'fabric_type' => $fabricType,
                        'width_cm' => $baseSpec?->width_cm ?? 140.00,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('material_supplier')->insertOrIgnore([
                        'material_id' => $newMaterialId,
                        'supplier_id' => $supplier->id,
                        'is_preferred' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Migrate this catalog's specific colors to the new material
                if (Schema::hasTable('supplier_fabric_catalog_colors')) {
                    $catColors = DB::table('supplier_fabric_catalog_colors')
                        ->where('supplier_fabric_catalog_id', $cat->id)
                        ->get();

                    foreach ($catColors as $cc) {
                        $internal = null;
                        if (! empty($cc->fabric_color_id)) {
                            $internal = DB::table('fabric_colors')->where('id', $cc->fabric_color_id)->first();
                        }

                        $colorCode = $internal?->color_code ?? (string) $cc->id;
                        $suppColorCode = $cc->supplier_color_code ?: "{$cat->catalog_number}-{$colorCode}";

                        DB::table('fabric_colors')->insertOrIgnore([
                            'material_id' => $newMaterialId,
                            'color_code' => $colorCode,
                            'supplier_color_code' => $suppColorCode,
                            'color_name_ar' => $cc->supplier_color_name ?? $internal?->color_name_ar ?? "لون {$colorCode}",
                            'hex_code' => $internal?->hex_code ?? '#cccccc',
                            'pattern' => $internal?->pattern ?? null,
                            'is_available' => $cc->is_available ?? true,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op to prevent accidental loss of newly migrated entities
    }

    private function generateUniqueMaterialCode(): string
    {
        $lastMaterial = DB::table('materials')
            ->where('code', 'LIKE', 'MAT-%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($lastMaterial && preg_match('/MAT-(\d+)/', $lastMaterial->code, $matches)) {
            $nextNum = ((int) $matches[1]) + 1;
        }

        $code = sprintf('MAT-%06d', $nextNum);
        while (DB::table('materials')->where('code', $code)->exists()) {
            $nextNum++;
            $code = sprintf('MAT-%06d', $nextNum);
        }

        return $code;
    }
};
