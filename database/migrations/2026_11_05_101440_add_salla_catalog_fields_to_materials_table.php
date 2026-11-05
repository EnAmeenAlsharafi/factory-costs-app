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
        Schema::table('materials', function (Blueprint $table) {
            $table->string('salla_product_id', 100)->nullable()->unique();
            $table->string('salla_category')->nullable();
            $table->string('salla_status', 100)->nullable();
            $table->decimal('sales_price', 15, 4)->nullable();
            $table->decimal('sales_reference_cost', 15, 4)->nullable();
            $table->decimal('salla_stock_quantity', 15, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['salla_product_id']);
            $table->dropColumn(['salla_product_id', 'salla_category', 'salla_status', 'sales_price', 'sales_reference_cost', 'salla_stock_quantity']);
        });
    }
};
