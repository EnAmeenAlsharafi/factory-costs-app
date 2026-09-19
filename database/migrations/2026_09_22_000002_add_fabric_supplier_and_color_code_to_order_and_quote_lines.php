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
        Schema::table('customer_order_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_order_lines', 'fabric_supplier_id')) {
                $table->foreignId('fabric_supplier_id')->nullable()->after('has_storage')->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_order_lines', 'fabric_color_code')) {
                $table->string('fabric_color_code', 100)->nullable()->after('fabric_color_id');
            }
            if (! Schema::hasColumn('customer_order_lines', 'fabric_notes')) {
                $table->text('fabric_notes')->nullable()->after('fabric_color_code');
            }
        });

        Schema::table('quotation_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_lines', 'fabric_supplier_id')) {
                $table->foreignId('fabric_supplier_id')->nullable()->after('has_storage')->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('quotation_lines', 'fabric_color_code')) {
                $table->string('fabric_color_code', 100)->nullable()->after('fabric_color_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('customer_order_lines', 'fabric_supplier_id')) {
                $table->dropForeign(['fabric_supplier_id']);
                $table->dropColumn('fabric_supplier_id');
            }
            if (Schema::hasColumn('customer_order_lines', 'fabric_color_code')) {
                $table->dropColumn('fabric_color_code');
            }
            if (Schema::hasColumn('customer_order_lines', 'fabric_notes')) {
                $table->dropColumn('fabric_notes');
            }
        });

        Schema::table('quotation_lines', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_lines', 'fabric_supplier_id')) {
                $table->dropForeign(['fabric_supplier_id']);
                $table->dropColumn('fabric_supplier_id');
            }
            if (Schema::hasColumn('quotation_lines', 'fabric_color_code')) {
                $table->dropColumn('fabric_color_code');
            }
        });
    }
};
