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
        if (! Schema::hasIndex('fabric_colors', 'fabric_colors_material_id_idx')) {
            Schema::table('fabric_colors', function (Blueprint $table) {
                $table->index('material_id', 'fabric_colors_material_id_idx');
            });
        }

        Schema::table('fabric_colors', function (Blueprint $table) {
            $table->unique(['material_id', 'color_code'], 'fabric_colors_material_code_unique');
        });

        Schema::create('supplier_fabric_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('catalog_number', 100);
            $table->string('catalog_name')->nullable();
            $table->string('supplier_material_code', 100)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['material_id', 'supplier_id', 'catalog_number'],
                'supplier_fabric_catalog_unique'
            );
            $table->index(['material_id', 'is_active']);
            $table->index(['supplier_id', 'is_active']);
        });

        Schema::create('supplier_fabric_catalog_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_fabric_catalog_id');
            $table->foreignId('fabric_color_id');
            $table->string('supplier_color_code', 100);
            $table->string('supplier_color_name')->nullable();
            $table->boolean('is_available')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['supplier_fabric_catalog_id', 'fabric_color_id'],
                'supplier_catalog_internal_color_unique'
            );
            $table->unique(
                ['supplier_fabric_catalog_id', 'supplier_color_code'],
                'supplier_catalog_color_code_unique'
            );
            $table->foreign('supplier_fabric_catalog_id', 'sfc_colors_catalog_fk')
                ->references('id')
                ->on('supplier_fabric_catalogs')
                ->cascadeOnDelete();
            $table->foreign('fabric_color_id', 'sfc_colors_fabric_color_fk')
                ->references('id')
                ->on('fabric_colors')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_fabric_catalog_colors');
        Schema::dropIfExists('supplier_fabric_catalogs');

        Schema::table('fabric_colors', function (Blueprint $table) {
            $table->dropUnique('fabric_colors_material_code_unique');
        });
    }
};
