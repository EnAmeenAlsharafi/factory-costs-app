<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `approved_recipe_version_id` is read/queried throughout RecipeService and
 * ManufacturingRecipeController (recipe edit/update/delete safety checks)
 * and exercised by multiple Feature tests, but the column was never
 * migrated onto `customer_order_lines`, causing a 500 (Unknown column) on
 * every recipe edit/update/delete whose version has no prior production
 * history. This restores the column the rest of the codebase already
 * expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('approved_recipe_version_id')->nullable()->after('product_configuration_id')
                ->constrained('manufacturing_recipe_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_recipe_version_id');
        });
    }
};
