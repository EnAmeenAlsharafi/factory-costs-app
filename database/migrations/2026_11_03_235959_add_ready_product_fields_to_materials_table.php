<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — ready-stock products (mattresses, pillows, toppers, ...) are
 * purchased goods sold as-is. They reuse the existing material/lot/movement
 * inventory engine rather than a parallel ledger; this flag marks which
 * materials are sellable ready products and excludes them from recipe/BOM
 * selectors by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('is_sellable_ready_product')->default(false)->index()->after('is_active');
            $table->string('sales_sku')->nullable()->index()->after('is_sellable_ready_product');
            $table->string('sales_display_name')->nullable()->after('sales_sku');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['is_sellable_ready_product', 'sales_sku', 'sales_display_name']);
        });
    }
};
