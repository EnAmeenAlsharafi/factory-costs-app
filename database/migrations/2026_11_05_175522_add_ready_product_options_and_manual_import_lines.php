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
            $table->boolean('has_ready_options')->default(false);
            $table->foreignId('ready_parent_id')->nullable()->constrained('materials')->restrictOnDelete();
            $table->string('ready_option_label')->nullable();
        });
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->json('manual_import_lines')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', fn (Blueprint $table) => $table->dropColumn('manual_import_lines'));
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ready_parent_id');
            $table->dropColumn(['has_ready_options', 'ready_option_label']);
        });
    }
};
