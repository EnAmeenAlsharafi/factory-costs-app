<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->boolean('special_fabric')->default(false);
        });
        DB::table('customer_order_lines')->where('fabric_notes', 'like', 'قماش العميل:%')->update(['special_fabric' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropColumn('special_fabric');
        });
    }
};
