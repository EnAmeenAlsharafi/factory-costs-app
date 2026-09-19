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
        Schema::table('material_receipt_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('material_receipt_lines', 'fabric_color_code')) {
                $table->string('fabric_color_code', 100)->nullable()->after('fabric_color_id');
            }
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_lots', 'fabric_color_code')) {
                $table->string('fabric_color_code', 100)->nullable()->after('fabric_color_id');
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'fabric_color_code')) {
                $table->string('fabric_color_code', 100)->nullable()->after('fabric_color_id');
            }
        });

        Schema::table('production_material_request_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('production_material_request_lines', 'fabric_color_code')) {
                $table->string('fabric_color_code', 100)->nullable()->after('fabric_color_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_receipt_lines', function (Blueprint $table) {
            if (Schema::hasColumn('material_receipt_lines', 'fabric_color_code')) {
                $table->dropColumn('fabric_color_code');
            }
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_lots', 'fabric_color_code')) {
                $table->dropColumn('fabric_color_code');
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'fabric_color_code')) {
                $table->dropColumn('fabric_color_code');
            }
        });

        Schema::table('production_material_request_lines', function (Blueprint $table) {
            if (Schema::hasColumn('production_material_request_lines', 'fabric_color_code')) {
                $table->dropColumn('fabric_color_code');
            }
        });
    }
};
