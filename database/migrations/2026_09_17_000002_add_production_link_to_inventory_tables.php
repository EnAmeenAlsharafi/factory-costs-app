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
        Schema::table('material_issues', function (Blueprint $table) {
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('production_material_request_id')->nullable()->constrained('production_material_requests')->nullOnDelete();
        });

        Schema::table('material_issue_lines', function (Blueprint $table) {
            $table->foreignId('production_material_request_line_id')->nullable()->constrained('production_material_request_lines', indexName: 'mil_pmr_line_id_fk')->nullOnDelete();
            $table->string('request_reason')->nullable();
        });

        Schema::table('material_returns', function (Blueprint $table) {
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('production_material_request_id')->nullable()->constrained('production_material_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_returns', function (Blueprint $table) {
            $table->dropForeign(['production_order_id']);
            $table->dropForeign(['production_material_request_id']);
            $table->dropColumn(['production_order_id', 'production_material_request_id']);
        });

        Schema::table('material_issue_lines', function (Blueprint $table) {
            $table->dropForeign('mil_pmr_line_id_fk');
            $table->dropColumn(['production_material_request_line_id', 'request_reason']);
        });

        Schema::table('material_issues', function (Blueprint $table) {
            $table->dropForeign(['production_order_id']);
            $table->dropForeign(['production_material_request_id']);
            $table->dropColumn(['production_order_id', 'production_material_request_id']);
        });
    }
};
