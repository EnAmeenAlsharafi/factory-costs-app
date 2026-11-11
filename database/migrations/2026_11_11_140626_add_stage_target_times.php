<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department target times: each production department has a target number of working days per order.
 * A stage takes its department's target the moment it reaches the department (becomes ready), and keeps
 * it, so changing a department's target later never moves the deadline of work already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_days')->nullable()->after('awards_points');
            $table->unsignedTinyInteger('warning_percent')->default(75)->after('target_days');
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_days')->nullable()->after('ready_at');
            $table->dateTime('due_at')->nullable()->after('target_days');
            $table->index('due_at', 'poo_due_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->dropIndex('poo_due_at_idx');
            $table->dropColumn(['target_days', 'due_at']);
        });
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn(['target_days', 'warning_percent']));
    }
};
