<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — a delivery order line now carries either a manufactured
 * production order or a ready-stock material, never both/neither
 * (enforced here via CHECK where supported, and in DeliveryOrderService in
 * a later stage). production_order_id becomes nullable so ready-stock-only
 * lines can exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_order_lines', function (Blueprint $table) {
            $table->foreignId('production_order_id')->nullable()->change();

            $table->enum('line_type', ['MANUFACTURED', 'READY_STOCK'])->default('MANUFACTURED')->after('production_order_id');
            $table->foreignId('material_id')->nullable()->after('line_type')->constrained('materials')->nullOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->after('material_id')->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable()->after('prepared_by_user_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE delivery_order_lines ADD CONSTRAINT delivery_order_lines_target_xor_check CHECK ((production_order_id IS NOT NULL AND material_id IS NULL) OR (production_order_id IS NULL AND material_id IS NOT NULL))');
        } elseif ($driver === 'sqlite') {
            DB::statement('CREATE TRIGGER delivery_order_lines_target_xor_check BEFORE INSERT ON delivery_order_lines
                WHEN NOT ((NEW.production_order_id IS NOT NULL AND NEW.material_id IS NULL) OR (NEW.production_order_id IS NULL AND NEW.material_id IS NOT NULL))
                BEGIN SELECT RAISE(ABORT, "delivery_order_lines: exactly one of production_order_id/material_id is required"); END');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE delivery_order_lines DROP CONSTRAINT delivery_order_lines_target_xor_check');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS delivery_order_lines_target_xor_check');
        }

        Schema::table('delivery_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by_user_id');
            $table->dropConstrainedForeignId('material_id');
            $table->dropColumn(['line_type', 'prepared_at']);

            $table->foreignId('production_order_id')->change();
        });
    }
};
