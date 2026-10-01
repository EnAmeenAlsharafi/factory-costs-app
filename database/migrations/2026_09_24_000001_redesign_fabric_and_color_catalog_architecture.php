<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update fabric_material_specs table
        Schema::table('fabric_material_specs', function (Blueprint $table) {
            if (! Schema::hasColumn('fabric_material_specs', 'supplier_id')) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('material_id')
                    ->constrained('suppliers')
                    ->restrictOnDelete();
            }
            if (! Schema::hasColumn('fabric_material_specs', 'catalog_number')) {
                $table->string('catalog_number', 100)->nullable()->after('supplier_id');
            }
            if (! Schema::hasColumn('fabric_material_specs', 'catalog_name')) {
                $table->string('catalog_name', 255)->nullable()->after('catalog_number');
            }
            if (! Schema::hasColumn('fabric_material_specs', 'catalog_image_path')) {
                $table->string('catalog_image_path', 255)->nullable()->after('catalog_name');
            }
        });

        // 2. Update fabric_colors table
        Schema::table('fabric_colors', function (Blueprint $table) {
            if (! Schema::hasColumn('fabric_colors', 'supplier_color_code')) {
                $table->string('supplier_color_code', 100)->nullable()->after('color_code');
            }
            if (! Schema::hasColumn('fabric_colors', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('is_active');
            }
        });

        // 3. Add operational snapshot column fabric_supplier_color_code
        $snapshotTables = [
            'customer_order_lines',
            'quotation_lines',
            'production_orders',
            'material_receipt_lines',
            'inventory_lots',
            'inventory_movements',
            'production_material_request_lines',
        ];

        foreach ($snapshotTables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'fabric_supplier_color_code')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('fabric_supplier_color_code', 100)->nullable()->after('fabric_color_code');
                });
            }
        }

        // 4. Safe Data Migration Phase
        $this->migrateExistingCatalogData();

        // 5. Apply Unique Constraints
        if (! $this->indexExists('fabric_material_specs', 'fabric_specs_supplier_catalog_unique')) {
            Schema::table('fabric_material_specs', function (Blueprint $table) {
                $table->unique(['supplier_id', 'catalog_number'], 'fabric_specs_supplier_catalog_unique');
            });
        }

        if (! $this->indexExists('fabric_colors', 'fabric_colors_material_code_unique')) {
            Schema::table('fabric_colors', function (Blueprint $table) {
                $table->unique(['material_id', 'color_code'], 'fabric_colors_material_code_unique');
            });
        }

        if (! $this->indexExists('fabric_colors', 'fabric_colors_mat_supp_code_unique')) {
            Schema::table('fabric_colors', function (Blueprint $table) {
                $table->unique(['material_id', 'supplier_color_code'], 'fabric_colors_mat_supp_code_unique');
            });
        }

        // 6. Backfill snapshot values in operational tables
        $this->backfillOperationalSnapshots();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('fabric_colors', 'fabric_colors_mat_supp_code_unique')) {
            Schema::table('fabric_colors', function (Blueprint $table) {
                $table->dropUnique('fabric_colors_mat_supp_code_unique');
            });
        }

        if ($this->indexExists('fabric_colors', 'fabric_colors_material_code_unique')) {
            Schema::table('fabric_colors', function (Blueprint $table) {
                $table->dropUnique('fabric_colors_material_code_unique');
            });
        }

        Schema::table('fabric_colors', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('fabric_colors', 'supplier_color_code')) {
                $columnsToDrop[] = 'supplier_color_code';
            }
            if (Schema::hasColumn('fabric_colors', 'is_available')) {
                $columnsToDrop[] = 'is_available';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        if ($this->indexExists('fabric_material_specs', 'fabric_specs_supplier_catalog_unique')) {
            Schema::table('fabric_material_specs', function (Blueprint $table) {
                $table->dropUnique('fabric_specs_supplier_catalog_unique');
            });
        }

        if (Schema::hasColumn('fabric_material_specs', 'supplier_id')) {
            Schema::table('fabric_material_specs', function (Blueprint $table) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn(['supplier_id', 'catalog_number', 'catalog_name', 'catalog_image_path']);
            });
        }

        $snapshotTables = [
            'customer_order_lines',
            'quotation_lines',
            'production_orders',
            'material_receipt_lines',
            'inventory_lots',
            'inventory_movements',
            'production_material_request_lines',
        ];

        foreach ($snapshotTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'fabric_supplier_color_code')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('fabric_supplier_color_code');
                });
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $idx) {
                if (($idx->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $indexes = DB::select("SHOW INDEXES FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return ! empty($indexes);
    }

    private function migrateExistingCatalogData(): void
    {
        if (! Schema::hasTable('supplier_fabric_catalogs')) {
            return;
        }

        $catalogs = DB::table('supplier_fabric_catalogs')->get();
        if ($catalogs->isEmpty()) {
            return;
        }

        foreach ($catalogs as $cat) {
            // Find material by stable code MAT-000018 if catalog 2020, else by material_id
            $material = null;
            if ($cat->catalog_number === '2020') {
                $material = DB::table('materials')->where('code', 'MAT-000018')->first();
            }

            if (! $material && ! empty($cat->material_id)) {
                $material = DB::table('materials')->where('id', $cat->material_id)->first();
            }

            if (! $material) {
                throw new RuntimeException(
                    "تعذر العثور على مادة القماش المرتبطة بالكتالوج رقم [{$cat->catalog_number}]. يرجى التحقق من وجود المادة MAT-000018."
                );
            }

            // Find supplier
            $supplier = null;
            if (! empty($cat->supplier_id)) {
                $supplier = DB::table('suppliers')->where('id', $cat->supplier_id)->first();
            }

            if (! $supplier && $cat->catalog_number === '2020') {
                $supplier = DB::table('suppliers')
                    ->where(function ($q) {
                        $q->where('name', 'like', '%إبداع السرير%')
                            ->orWhere('name', 'like', '%ابداع السرير%');
                    })
                    ->first();
            }

            if (! $supplier) {
                throw new RuntimeException(
                    "تعذر العثور على المورد المرتبط بالكتالوج رقم [{$cat->catalog_number}]. يرجى التحقق من وجود مورد 'إبداع السرير'."
                );
            }

            // Get fabric spec
            $spec = DB::table('fabric_material_specs')->where('material_id', $material->id)->first();
            $fabricType = $spec?->fabric_type ?: 'شانيل';

            // Standard name format: قماش {نوع القماش} - {اسم المورد} - {رقم الكتالوج}
            // Preserve supplier name as recorded (e.g. ابداع السرير or إبداع السرير)
            $standardName = "قماش {$fabricType} - {$supplier->name} - {$cat->catalog_number}";

            DB::table('materials')->where('id', $material->id)->update([
                'name_ar' => $standardName,
                'updated_at' => now(),
            ]);

            // Update fabric_material_specs with supplier and catalog
            DB::table('fabric_material_specs')->updateOrInsert(
                ['material_id' => $material->id],
                [
                    'supplier_id' => $supplier->id,
                    'catalog_number' => (string) $cat->catalog_number,
                    'catalog_name' => $cat->catalog_name ?: $standardName,
                    'catalog_image_path' => $cat->image_path,
                    'fabric_type' => $fabricType,
                    'width_cm' => $spec?->width_cm ?? 140.00,
                    'updated_at' => now(),
                ]
            );

            // Maintain material_supplier record for compatibility
            DB::table('material_supplier')->updateOrInsert(
                [
                    'material_id' => $material->id,
                    'supplier_id' => $supplier->id,
                ],
                [
                    'is_preferred' => true,
                    'updated_at' => now(),
                ]
            );

            // Migrate colors from supplier_fabric_catalog_colors
            if (Schema::hasTable('supplier_fabric_catalog_colors')) {
                $catColors = DB::table('supplier_fabric_catalog_colors')
                    ->where('supplier_fabric_catalog_id', $cat->id)
                    ->get();

                foreach ($catColors as $catColor) {
                    $fc = null;
                    if (! empty($catColor->fabric_color_id)) {
                        $fc = DB::table('fabric_colors')->where('id', $catColor->fabric_color_id)->first();
                    }

                    if (! $fc) {
                        $extractedCode = last(explode('-', (string) $catColor->supplier_color_code));
                        $fc = DB::table('fabric_colors')
                            ->where('material_id', $material->id)
                            ->where('color_code', $extractedCode)
                            ->first();
                    }

                    if ($fc) {
                        DB::table('fabric_colors')->where('id', $fc->id)->update([
                            'supplier_color_code' => $catColor->supplier_color_code,
                            'is_available' => true,
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);
                    } else {
                        $extractedCode = last(explode('-', (string) $catColor->supplier_color_code));
                        DB::table('fabric_colors')->insert([
                            'material_id' => $material->id,
                            'color_code' => $extractedCode,
                            'color_name_ar' => 'لون '.$extractedCode,
                            'supplier_color_code' => $catColor->supplier_color_code,
                            'is_available' => true,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Explicit verification for catalog 2020: 11 colors from 1 to 11
                if ($cat->catalog_number === '2020') {
                    for ($i = 1; $i <= 11; $i++) {
                        $expectedCode = (string) $i;
                        $expectedSupplierCode = "2020-{$i}";

                        $colorRow = DB::table('fabric_colors')
                            ->where('material_id', $material->id)
                            ->where('color_code', $expectedCode)
                            ->first();

                        if (! $colorRow) {
                            throw new RuntimeException("فشل التحقق: اللون الداخلي [{$expectedCode}] غير موجود للكتالوج 2020.");
                        }

                        if ($colorRow->supplier_color_code !== $expectedSupplierCode) {
                            throw new RuntimeException(
                                "فشل التحقق: كود المورد للون [{$expectedCode}] غير متطابق. المتوقع: [{$expectedSupplierCode}]، الفعلي: [{$colorRow->supplier_color_code}]."
                            );
                        }
                    }
                }
            }
        }
    }

    private function backfillOperationalSnapshots(): void
    {
        $tables = [
            'customer_order_lines',
            'quotation_lines',
            'production_orders',
            'material_receipt_lines',
            'inventory_lots',
            'inventory_movements',
            'production_material_request_lines',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'fabric_color_id') && Schema::hasColumn($tbl, 'fabric_supplier_color_code')) {
                if (DB::getDriverName() === 'sqlite') {
                    DB::statement("
                        UPDATE \"{$tbl}\"
                        SET \"fabric_supplier_color_code\" = (
                            SELECT supplier_color_code
                            FROM fabric_colors
                            WHERE fabric_colors.id = \"{$tbl}\".fabric_color_id
                        )
                        WHERE \"fabric_color_id\" IS NOT NULL
                          AND \"fabric_supplier_color_code\" IS NULL
                          AND EXISTS (
                              SELECT 1 FROM fabric_colors WHERE fabric_colors.id = \"{$tbl}\".fabric_color_id
                          )
                    ");
                } else {
                    DB::statement("
                        UPDATE `{$tbl}`
                        JOIN `fabric_colors` ON `{$tbl}`.`fabric_color_id` = `fabric_colors`.`id`
                        SET `{$tbl}`.`fabric_supplier_color_code` = `fabric_colors`.`supplier_color_code`
                        WHERE `{$tbl}`.`fabric_color_id` IS NOT NULL
                          AND `{$tbl}`.`fabric_supplier_color_code` IS NULL
                    ");
                }
            }
        }
    }
};
