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
        Schema::table('product_models', function (Blueprint $table) {
            if (! Schema::hasColumn('product_models', 'requires_fabric_selection')) {
                $table->boolean('requires_fabric_selection')->default(false)->after('is_custom_template');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_models', function (Blueprint $table) {
            if (Schema::hasColumn('product_models', 'requires_fabric_selection')) {
                $table->dropColumn('requires_fabric_selection');
            }
        });
    }
};
