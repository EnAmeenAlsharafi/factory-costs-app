<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Worker points: beds are grouped into point categories (a barrier bed base counts 0.5, a padded
 * bed 1.2, ...). Completing work in a department that awards points credits the worker with
 * quantity × category points, snapshotted on the progress record so later rate edits never
 * rewrite a closed month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_point_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->decimal('points', 6, 2);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('product_models', function (Blueprint $table) {
            $table->foreignId('point_category_id')->nullable()->after('is_custom_template')
                ->constrained('production_point_categories', indexName: 'pm_point_category_fk')->nullOnDelete();
        });

        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->foreignId('point_category_id')->nullable()
                ->constrained('production_point_categories', indexName: 'col_point_category_fk')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('awards_points')->default(false)->after('is_production_department');
        });

        // MySQL created occurred_at as "ON UPDATE CURRENT_TIMESTAMP", so any later edit of a progress
        // record (the backfill below, crediting points) would silently move it to today. Keep it fixed.
        Schema::table('production_operation_progress', function (Blueprint $table) {
            $table->timestamp('occurred_at')->useCurrent()->change();
        });

        Schema::table('production_operation_progress', function (Blueprint $table) {
            $table->foreignId('worker_user_id')->nullable()->after('user_id')
                ->constrained('users', indexName: 'pop_worker_user_fk')->nullOnDelete();
            $table->foreignId('point_category_id')->nullable()->after('worker_user_id')
                ->constrained('production_point_categories', indexName: 'pop_point_category_fk')->nullOnDelete();
            $table->decimal('points_per_unit', 6, 2)->nullable()->after('point_category_id');
            $table->decimal('points', 10, 2)->nullable()->after('points_per_unit');
            $table->index(['worker_user_id', 'occurred_at'], 'pop_worker_occurred_idx');
        });

        $now = now();
        DB::table('production_point_categories')->insert([
            ['name_ar' => 'قاعدة سرير حواجز', 'points' => 0.5, 'description' => 'القاعدة وحدها دون الظهر.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name_ar' => 'ظهر سرير', 'points' => 0.5, 'description' => 'الظهر وحده.', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name_ar' => 'سرير حواجز', 'points' => 1, 'description' => 'السرير الكامل بالحواجز.', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name_ar' => 'سرير مضخم', 'points' => 1.2, 'description' => 'سرير منجد بالكامل يحتاج وقتاً أطول.', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('departments')->where('code', 'UPHOLSTERY')->update(['awards_points' => true]);

        // Who did the work: the employee the operation was assigned to, else whoever recorded it.
        DB::table('production_operation_progress')->update([
            'occurred_at' => DB::raw('occurred_at'),
            'worker_user_id' => DB::raw('COALESCE((SELECT assigned_user_id FROM production_order_operations WHERE production_order_operations.id = production_operation_progress.production_order_operation_id), user_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('production_operation_progress', function (Blueprint $table) {
            $table->dropForeign('pop_worker_user_fk');
            $table->dropForeign('pop_point_category_fk');
            $table->dropIndex('pop_worker_occurred_idx');
            $table->dropColumn(['worker_user_id', 'point_category_id', 'points_per_unit', 'points']);
        });
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn('awards_points'));
        Schema::table('customer_order_lines', function (Blueprint $table) {
            $table->dropForeign('col_point_category_fk');
            $table->dropColumn('point_category_id');
        });
        Schema::table('product_models', function (Blueprint $table) {
            $table->dropForeign('pm_point_category_fk');
            $table->dropColumn('point_category_id');
        });
        Schema::dropIfExists('production_point_categories');
    }
};
