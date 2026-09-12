<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Central Materials Catalog
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index(); // e.g. 'MAT-000001'
            $table->foreignId('material_category_id')->constrained('material_categories')->restrictOnDelete();

            $table->string('name_ar')->index();
            $table->string('name_en')->nullable();

            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();

            $table->decimal('min_stock_level', 12, 4)->default(0);
            $table->decimal('reorder_point', 12, 4)->default(0);

            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // 2. Wood Material Specifications (1-to-1 with materials)
        Schema::create('wood_material_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained('materials')->cascadeOnDelete();
            $table->string('wood_type'); // e.g. MDF, Swedish Wood, Counter, Beech
            $table->decimal('thickness_mm', 8, 2);
            $table->decimal('width_cm', 8, 2);
            $table->decimal('length_cm', 8, 2);
            $table->string('grade')->nullable();
            $table->timestamps();
        });

        // 3. Foam Material Specifications (1-to-1 with materials)
        Schema::create('foam_material_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained('materials')->cascadeOnDelete();
            $table->string('foam_type'); // e.g. Standard Foam, Rebonded Foam, Density 30
            $table->decimal('density_kg_m3', 8, 2)->nullable();
            $table->string('hardness_rating')->nullable(); // e.g. Soft, Medium, Firm
            $table->decimal('thickness_mm', 8, 2);
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->string('block_dimensions')->nullable(); // Optional human-readable notes e.g. "200x120"
            $table->timestamps();
        });

        // 4. Fabric Material Specifications (1-to-1 with materials)
        Schema::create('fabric_material_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained('materials')->cascadeOnDelete();
            $table->string('fabric_type'); // e.g. Velvet, Linen, Leatherette
            $table->string('pattern_type')->nullable(); // e.g. Plain, Striped, Floral
            $table->decimal('width_cm', 8, 2); // Standard roll width e.g. 140cm
            $table->decimal('weight_gsm', 8, 2)->nullable();
            $table->string('composition')->nullable();
            $table->integer('martindale_rub_count')->nullable();
            $table->timestamps();
        });

        // 5. Fabric Colors (1-to-many with materials where category = FABRIC)
        Schema::create('fabric_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->string('color_code');
            $table->string('color_name_ar');
            $table->string('color_name_en')->nullable();
            $table->string('hex_code', 7)->nullable();
            $table->string('pattern')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Material Supplier Mapping (Many-to-many) - NO PRICE COLUMNS (Rule #39)
        Schema::create('material_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_item_code')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->decimal('minimum_order_qty', 12, 4)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'supplier_id']);
        });

        // 7. Material-Specific Unit Conversions (e.g. 1 ROLL = 50 METERS for Material X)
        Schema::create('material_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('conversion_factor', 12, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'from_unit_id', 'to_unit_id'], 'mat_unit_conv_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_unit_conversions');
        Schema::dropIfExists('material_supplier');
        Schema::dropIfExists('fabric_colors');
        Schema::dropIfExists('fabric_material_specs');
        Schema::dropIfExists('foam_material_specs');
        Schema::dropIfExists('wood_material_specs');
        Schema::dropIfExists('materials');
    }
};
