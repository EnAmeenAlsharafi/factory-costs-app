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
        // 1. Semi-Finished Components
        Schema::create('semi_finished_components', function (Blueprint $table) {
            $table->id();
            $table->string('component_code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->boolean('has_storage')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Manufacturing Recipes (BOM Header)
        Schema::create('manufacturing_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('recipe_code')->unique();
            $table->enum('target_type', ['PRODUCT_CONFIGURATION', 'SEMI_FINISHED_COMPONENT'])->default('PRODUCT_CONFIGURATION');
            $table->foreignId('product_configuration_id')->nullable()->constrained('product_configurations')->nullOnDelete();
            $table->foreignId('semi_finished_component_id')->nullable()->constrained('semi_finished_components')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Manufacturing Recipe Versions
        Schema::create('manufacturing_recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturing_recipe_id')->constrained('manufacturing_recipes')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->enum('status', ['DRAFT', 'APPROVED', 'SUPERSEDED', 'INACTIVE'])->default('DRAFT');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['manufacturing_recipe_id', 'version_number'], 'mfg_recipe_ver_unique');
        });

        // 4. Manufacturing Recipe Items
        Schema::create('manufacturing_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained('manufacturing_recipe_versions')->cascadeOnDelete();
            $table->enum('item_type', ['MATERIAL', 'SEMI_FINISHED_COMPONENT'])->default('MATERIAL');
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('semi_finished_component_id')->nullable()->constrained('semi_finished_components')->nullOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->foreignId('unit_id')->constrained('units_of_measure');
            $table->decimal('waste_percentage', 5, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 5. Manufacturing Templates
        Schema::create('manufacturing_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Manufacturing Template Items
        Schema::create('manufacturing_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturing_template_id')->constrained('manufacturing_templates')->cascadeOnDelete();
            $table->enum('item_type', ['MATERIAL', 'SEMI_FINISHED_COMPONENT'])->default('MATERIAL');
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('semi_finished_component_id')->nullable()->constrained('semi_finished_components')->nullOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->foreignId('unit_id')->constrained('units_of_measure');
            $table->decimal('waste_percentage', 5, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_template_items');
        Schema::dropIfExists('manufacturing_templates');
        Schema::dropIfExists('manufacturing_recipe_items');
        Schema::dropIfExists('manufacturing_recipe_versions');
        Schema::dropIfExists('manufacturing_recipes');
        Schema::dropIfExists('semi_finished_components');
    }
};
