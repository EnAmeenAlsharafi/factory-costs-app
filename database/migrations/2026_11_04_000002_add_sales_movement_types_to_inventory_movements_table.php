<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — adds the two ready-stock sales movement types to the
 * inventory_movements.movement_type enum (SALES_DISPATCH / SALES_RETURN),
 * alongside the six existing values. This is the first migration in the
 * project's history to alter an existing enum column rather than define one
 * on table creation, so it is isolated here and proven round-trip safe
 * (migrate -> rollback -> migrate) before anything else in this stage.
 */
return new class extends Migration
{
    private const OLD_VALUES = ['RECEIPT', 'ISSUE', 'RETURN', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT', 'OPENING_BALANCE'];

    private const NEW_VALUES = [...self::OLD_VALUES, 'SALES_DISPATCH', 'SALES_RETURN'];

    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('movement_type', self::NEW_VALUES)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('movement_type', self::OLD_VALUES)->change();
        });
    }
};
