<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — order lines can now be a manufactured bed, a ready-stock
 * product, or a bundle parent carrying its children's price. Existing rows
 * default to MANUFACTURED (unchanged behavior). Width/length become
 * DB-nullable (enforced per-type instead in CustomerOrderRequest) because a
 * READY_STOCK/BUNDLE line never has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->enum('line_type', ['MANUFACTURED', 'READY_STOCK', 'BUNDLE'])->default('MANUFACTURED')->after('customer_order_id');
            $table->foreignId('material_id')->nullable()->after('customer_product_alias_id')->constrained('materials')->nullOnDelete();
            $table->foreignId('parent_line_id')->nullable()->after('material_id')->constrained('customer_order_lines')->nullOnDelete();
            $table->string('external_line_reference')->nullable()->index()->after('parent_line_id');
            $table->string('external_product_name')->nullable()->after('external_line_reference');
            $table->string('external_sku')->nullable()->index()->after('external_product_name');
            $table->boolean('is_incomplete')->default(false)->index()->after('external_sku');

            $table->decimal('requested_width_cm', 8, 2)->nullable()->change();
            $table->decimal('requested_length_cm', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_line_id');
            $table->dropConstrainedForeignId('material_id');
            $table->dropColumn(['line_type', 'external_line_reference', 'external_product_name', 'external_sku', 'is_incomplete']);

            $table->decimal('requested_width_cm', 8, 2)->change();
            $table->decimal('requested_length_cm', 8, 2)->change();
        });
    }
};
