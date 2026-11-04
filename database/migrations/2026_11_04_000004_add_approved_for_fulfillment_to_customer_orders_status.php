<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — adds APPROVED_FOR_FULFILLMENT to customer_orders.status for
 * orders with no manufactured lines (e.g. ready-stock-only Salla orders),
 * which must not sit stuck in production review.
 */
return new class extends Migration
{
    private const OLD_VALUES = ['DRAFT', 'PENDING_PRODUCTION_REVIEW', 'NEEDS_CHANGES', 'APPROVED_FOR_PRODUCTION', 'CANCELLED'];

    private const NEW_VALUES = [...self::OLD_VALUES, 'APPROVED_FOR_FULFILLMENT'];

    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->enum('status', self::NEW_VALUES)->default('DRAFT')->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->enum('status', self::OLD_VALUES)->default('DRAFT')->change();
        });
    }
};
